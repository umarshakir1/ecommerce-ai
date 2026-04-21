<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\AIService;
use App\Services\IntentService;
use App\Services\VectorSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * ChatController
 *
 * Orchestrates intent-aware chat:
 *  1. Validate incoming message
 *  2. Classify conversational intent (IntentService)
 *  3. Non-shopping  -> conversational reply, no products
 *  4. Shopping      -> full RAG pipeline (embed, search, respond)
 *  5. Persist conversation history
 *  6. Return JSON response
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly AIService           $aiService,
        private readonly IntentService       $intentService,
        private readonly VectorSearchService $vectorSearchService,
    ) {}

    // -------------------------------------------------------------------------
    // API ENDPOINT
    // -------------------------------------------------------------------------

    /**
     * POST /api/chat
     *
     * Request body:
     *   { "message": "I want a black hoodie in large size" }
     *
     * Response:
     *   {
     *     "reply": "...",
     *     "products": [...],
     *     "intent": {...},
     *     "session_id": "..."
     *   }
     */
    public function chat(Request $request): JsonResponse
    {
        // ── 1. Validate ──────────────────────────────────────────────────────
        $validator = Validator::make($request->all(), [
            'message' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'  => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userMessage = trim($request->input('message'));
        $sessionId   = $request->input('session_id') ?: $request->session()->getId();
        $clientId    = $request->attributes->get('client_id');

        try {
            // ── 2. Load conversation history first (needed for context-aware classification)
            $history = $this->getConversationHistory($sessionId);

            // ── 3. Classify conversational intent with prior context ───────────
            // Passing the last 4 turns lets the classifier correctly handle
            // follow-ups like "what about in blue?" after a hoodie discussion.
            $conversationalIntent = $this->intentService->classify(
                $userMessage,
                array_slice($history, -4)
            );
            Log::info('Conversational intent classified', [
                'intent'  => $conversationalIntent,
                'session' => $sessionId,
            ]);

            // ── 4. Non-shopping path ─────────────────────────────────────────
            // Greetings, casual talk, unrelated messages, and general questions
            // get a conversational reply with NO product search whatsoever.
            if (!$this->intentService->isShopping($conversationalIntent)) {
                $aiReply = $this->aiService->generateConversationalResponse(
                    $userMessage,
                    $conversationalIntent,
                    $history
                );

                $this->saveConversation($sessionId, 'user', $userMessage, [
                    'conversational_intent' => $conversationalIntent,
                ]);
                $this->saveConversation($sessionId, 'assistant', $aiReply);

                return response()->json([
                    'reply'      => $aiReply,
                    'products'   => [],
                    'intent'     => ['conversational_intent' => $conversationalIntent],
                    'session_id' => $sessionId,
                ]);
            }

            // ── 5. Shopping path: full RAG pipeline ──────────────────────────

            // 5a. Enrich follow-up queries with prior conversation context.
            //     "what about in white?" → "I want a black hoodie. what about in white?"
            //     This gives the embedding and intent extractor enough signal.
            $contextualQuery = $this->buildContextualQuery($userMessage, $history);

            // 5b. Extract structured query attributes (color, size, category …)
            $intent = $this->aiService->extractIntent($contextualQuery);
            Log::info('Shopping intent extracted', ['intent' => $intent, 'session' => $sessionId]);

            // 5c. Generate query embedding from enriched contextual text
            $embeddingText  = $this->buildEmbeddingText($contextualQuery, $intent);
            $queryEmbedding = $this->aiService->generateEmbedding($embeddingText);

            // 5d. Hybrid vector + SQL search — always scoped to this client
            $products = [];
            if ($queryEmbedding !== null) {
                $products = $this->vectorSearchService->search($queryEmbedding, $intent, $clientId);
            }

            // Fallback: pure SQL filter when embedding is unavailable
            if (empty($products)) {
                $products = $this->fallbackSqlSearch($intent, $clientId);
            }

            // 5e. Generate RAG response — full history gives the AI awareness
            //     of what was already recommended so it avoids repetition.
            $aiReply = $this->aiService->generateRAGResponse(
                $userMessage,
                $products,
                $history
            );

            // ── 6. Persist conversation turns ────────────────────────────────
            $this->saveConversation($sessionId, 'user', $userMessage, array_merge(
                $intent,
                ['conversational_intent' => $conversationalIntent]
            ));
            $this->saveConversation($sessionId, 'assistant', $aiReply, null, $products);

            // ── 7. Format and return ─────────────────────────────────────────
            $formattedProducts = $this->formatProducts($products);

            return response()->json([
                'reply'      => $aiReply,
                'products'   => $formattedProducts,
                'intent'     => $intent,
                'session_id' => $sessionId,
            ]);

        } catch (\Throwable $e) {
            Log::error('ChatController::chat error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'An error occurred while processing your request. Please try again.',
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // STREAMING CHAT ENDPOINT (SSE)
    // -------------------------------------------------------------------------

    /**
     * POST /api/chat/stream
     *
     * Streaming variant of chat().  Uses Server-Sent Events so the browser
     * receives tokens in real-time.  Internally:
     *   1. Runs classify+extract AND embedding generation in PARALLEL (Http::pool)
     *   2. Runs vector search (fast, local DB)
     *   3. Streams the final LLM response token-by-token
     *
     * SSE event types emitted:
     *   intent   – extracted intent attributes (sent immediately)
     *   products – matched product array (sent before streaming text)
     *   token    – one text chunk { text: "..." }
     *   toast    – UI notification { message, type }
     *   done     – signals stream end { session_id }
     *   error    – fatal error { message }
     */
    public function streamChat(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->stream(function () use ($validator) {
                $this->sendSSE('error', [
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ]);
            }, 422, $this->sseHeaders());
        }

        $userMessage = trim($request->input('message'));
        $sessionId   = $request->input('session_id') ?: $request->session()->getId();
        $clientId    = $request->attributes->get('client_id');

        return response()->stream(function () use ($userMessage, $sessionId, $clientId) {
            // Kill all output buffering layers so bytes reach the browser immediately
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            try {
                // ── 1. Load conversation history ─────────────────────────────
                $history         = $this->getConversationHistory($sessionId);
                $contextualQuery = $this->buildContextualQuery($userMessage, $history);

                // ── 2. PARALLEL: classify+extract AND embedding ───────────────
                [$combined, $embedding] = $this->aiService->classifyExtractAndEmbed(
                    $contextualQuery,
                    $contextualQuery,
                    array_slice($history, -4)
                );

                $conversationalIntent = $combined['conversational_intent'] ?? 'product_search';

                Log::info('streamChat classified', [
                    'intent'  => $conversationalIntent,
                    'session' => $sessionId,
                ]);

                // Send extracted attributes immediately so the UI can show them
                $this->sendSSE('intent', [
                    'conversational_intent' => $conversationalIntent,
                    'color'       => $combined['color']       ?? null,
                    'size'        => $combined['size']        ?? null,
                    'category'    => $combined['category']    ?? null,
                    'gender'      => $combined['gender']      ?? null,
                    'price_range' => $combined['price_range'] ?? null,
                ]);

                // ── 3. Non-shopping path: stream conversational reply ─────────
                if (!in_array($conversationalIntent, ['product_search', 'recommendation'], true)) {
                    $fullReply = '';

                    foreach ($this->aiService->generateConversationalResponseStream(
                        $userMessage,
                        $conversationalIntent,
                        $history
                    ) as $token) {
                        $fullReply .= $token;
                        $this->sendSSE('token', ['text' => $token]);
                    }

                    $this->sendSSE('done', ['products' => [], 'session_id' => $sessionId]);

                    $this->saveConversation($sessionId, 'user', $userMessage, [
                        'conversational_intent' => $conversationalIntent,
                    ]);
                    $this->saveConversation($sessionId, 'assistant', $fullReply);
                    return;
                }

                // ── 4. Shopping path: vector search ──────────────────────────
                $intent   = $combined;
                $products = [];

                if ($embedding !== null) {
                    $products = $this->vectorSearchService->search($embedding, $intent, $clientId);
                }

                if (empty($products)) {
                    $products = $this->fallbackSqlSearch($intent, $clientId);
                }

                // Send products BEFORE streaming the text so cards appear first
                $this->sendSSE('products', ['products' => $this->formatProducts($products)]);

                if (!empty($products)) {
                    $count = count($products);
                    $this->sendSSE('toast', [
                        'message' => "Found {$count} matching product" . ($count > 1 ? 's' : ''),
                        'type'    => 'success',
                    ]);
                }

                // ── 5. Stream RAG response ────────────────────────────────────
                $fullReply = '';

                foreach ($this->aiService->generateRAGResponseStream($userMessage, $products, $history) as $token) {
                    $fullReply .= $token;
                    $this->sendSSE('token', ['text' => $token]);
                }

                $this->sendSSE('done', ['session_id' => $sessionId]);

                // ── 6. Persist conversation ───────────────────────────────────
                $this->saveConversation($sessionId, 'user', $userMessage, array_merge(
                    $intent,
                    ['conversational_intent' => $conversationalIntent]
                ));
                $this->saveConversation($sessionId, 'assistant', $fullReply, null, $products);

            } catch (\Throwable $e) {
                Log::error('ChatController::streamChat error', [
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);
                $this->sendSSE('error', ['message' => 'An error occurred. Please try again.']);
            }
        }, 200, $this->sseHeaders());
    }

    // -------------------------------------------------------------------------
    // CONVERSATION HISTORY ENDPOINT
    // -------------------------------------------------------------------------

    /**
     * GET /api/chat/history
     * Returns the conversation history for the current session.
     */
    public function history(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId();
        $turns     = Conversation::where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get(['role', 'message', 'products', 'created_at']);

        return response()->json(['history' => $turns]);
    }

    /**
     * DELETE /api/chat/history
     * Clears the conversation history for the current session.
     */
    public function clearHistory(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId();
        Conversation::where('session_id', $sessionId)->delete();

        return response()->json(['message' => 'Conversation cleared.']);
    }

    // -------------------------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------------------------

    /**
     * Enrich a follow-up query with prior conversation context so the
     * embedding and intent extractor have enough signal to work with.
     *
     * Examples:
     *   history last user msg: "I want a black hoodie size L"
     *   current msg:           "what about in white?"
     *   result:                "I want a black hoodie size L. what about in white?"
     *
     * Only triggers when the current message looks like a follow-up
     * (short, or contains referential language). Standalone queries
     * are returned unchanged.
     */
    private function buildContextualQuery(string $currentMessage, array $history): string
    {
        if (empty($history)) {
            return $currentMessage;
        }

        // Find the last user turn in history
        $lastUserMessage = null;
        foreach (array_reverse($history) as $turn) {
            if (($turn['role'] ?? '') === 'user') {
                $lastUserMessage = $turn['content'];
                break;
            }
        }

        if ($lastUserMessage === null || $lastUserMessage === $currentMessage) {
            return $currentMessage;
        }

        $msgLower = strtolower(trim($currentMessage));

        // If the message clearly expresses a new, standalone purchase/search intent,
        // never treat it as a follow-up regardless of length.
        $newSearchIndicators = [
            'i want to buy', 'i want to get', 'i want a ', 'i want an ',
            'i need a ', 'i need an ', 'i am looking', "i'm looking",
            'looking for', 'find me', 'search for', 'do you sell',
            'buy a ', 'buy an ', 'purchase', 'get me a', 'show me a',
            'can i get', 'can i buy', 'where can i',
        ];
        foreach ($newSearchIndicators as $indicator) {
            if (str_contains($msgLower, $indicator)) {
                return $currentMessage;
            }
        }

        // Signals that the message IS a follow-up rather than a new query
        $followUpPhrases = [
            'what about', 'how about', 'and also', 'any in',
            'instead', 'same but', 'cheaper', 'more expensive', 'similar',
            'different color', 'different size', 'in blue', 'in red',
            'in black', 'in white', 'in green', 'in yellow', 'in pink',
            'in large', 'in small', 'in xl', 'in xs', 'in xxl',
            'any other', 'can you show', 'something else',
            'what is the price', 'how much', 'its price', 'the price',
        ];

        // Only auto-treat as follow-up if very short (< 30 chars) — reduces false positives
        $isFollowUp = mb_strlen($currentMessage) < 30;

        if (!$isFollowUp) {
            foreach ($followUpPhrases as $phrase) {
                if (str_contains($msgLower, $phrase)) {
                    $isFollowUp = true;
                    break;
                }
            }
        }

        return $isFollowUp
            ? "{$lastUserMessage}. {$currentMessage}"
            : $currentMessage;
    }

    /**
     * Build an enriched text string for embedding from query + intent.
     * More context in the embedding text = better semantic search results.
     */
    private function buildEmbeddingText(string $query, array $intent): string
    {
        $parts = [$query];

        if (!empty($intent['brand'])) {
            $parts[] = "brand: {$intent['brand']}";
        }
        if (!empty($intent['color'])) {
            $parts[] = "color: {$intent['color']}";
        }
        if (!empty($intent['size'])) {
            $parts[] = "size: {$intent['size']}";
        }
        if (!empty($intent['category'])) {
            $parts[] = "category: {$intent['category']}";
        }
        if (!empty($intent['keywords'])) {
            $parts[] = implode(' ', (array) $intent['keywords']);
        }

        return implode('. ', $parts);
    }

    /**
     * Fallback search using pure SQL when embedding is unavailable.
     * Filters by color/size/category and returns up to 5 products.
     * ALWAYS scoped to client_id — no cross-tenant access.
     */
    private function fallbackSqlSearch(array $intent, string $clientId): array
    {
        // SKU match takes priority — exact then partial LIKE fallback
        if (!empty($intent['sku'])) {
            $skuUpper = strtoupper($intent['sku']);

            $product = \App\Models\Product::where('client_id', $clientId)
                ->whereRaw('UPPER(sku) = ?', [$skuUpper])
                ->first();

            if (! $product) {
                $product = \App\Models\Product::where('client_id', $clientId)
                    ->whereRaw('UPPER(sku) LIKE ?', ['%' . $skuUpper . '%'])
                    ->first();
            }

            if ($product) {
                return [$product->toArray()];
            }
        }

        $query = \App\Models\Product::where('in_stock', true)
            ->where('client_id', $clientId);

        if (!empty($intent['brand'])) {
            $query->whereRaw('LOWER(brand) LIKE ?', ['%' . strtolower($intent['brand']) . '%']);
        }
        if (!empty($intent['color'])) {
            $query->whereRaw('LOWER(color) LIKE ?', ['%' . strtolower($intent['color']) . '%']);
        }
        if (!empty($intent['size'])) {
            $query->whereRaw('LOWER(size) = ?', [strtolower($intent['size'])]);
        }
        if (!empty($intent['category'])) {
            $query->whereRaw('LOWER(category) LIKE ?', ['%' . strtolower($intent['category']) . '%']);
        }

        return $query->orderByDesc('popularity')->limit(5)->get()->toArray();
    }

    /**
     * Retrieve conversation history for a session formatted as chat messages.
     */
    private function getConversationHistory(string $sessionId): array
    {
        return Conversation::where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($turn) => [
                'role'    => $turn->role,
                'content' => $turn->message,
            ])
            ->toArray();
    }

    /**
     * Persist a single conversation turn to the database.
     */
    private function saveConversation(
        string $sessionId,
        string $role,
        string $message,
        ?array $intent = null,
        array $products = []
    ): void {
        Conversation::create([
            'session_id'        => $sessionId,
            'role'              => $role,
            'message'           => $message,
            'extracted_intent'  => $intent,
            'products'          => $role === 'assistant' && !empty($products)
                ? array_map(fn ($p) => ['id' => $p['id'] ?? null, 'name' => $p['name'] ?? ''], $products)
                : null,
        ]);
    }

    /**
     * Clean up product arrays before returning in JSON response.
     * Removes the raw embedding vector to keep the payload small.
     */
    private function formatProducts(array $products): array
    {
        return array_map(function (array $product) {
            unset($product['embedding']); // never send raw vectors to the client
            return $product;
        }, $products);
    }

    /**
     * Emit a single SSE event to the current output stream.
     */
    private function sendSSE(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Standard headers required for Server-Sent Events.
     */
    private function sseHeaders(): array
    {
        return [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ];
    }
}
