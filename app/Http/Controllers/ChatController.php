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
            $conversationalIntent = $this->intentService->classify(
                $userMessage,
                array_slice($history, -6)
            );
            Log::info('Conversational intent classified', [
                'intent'  => $conversationalIntent,
                'session' => $sessionId,
            ]);

            // Promote 'question' intent to 'product_search' when recent products
            // exist in the session — user is asking about something they just saw.
            $promotedContextProducts = [];
            if ($conversationalIntent === 'question') {
                $promotedContextProducts = $this->getRecentContextProducts($sessionId, $clientId);
                if (!empty($promotedContextProducts)) {
                    $conversationalIntent = 'product_search';
                }
            }

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
            // Seed with promoted context products (from question→product_search promotion)
            // or short-circuit on explicit follow-up references.
            $products = $promotedContextProducts;
            if (empty($products) && $this->isFollowUpReference($userMessage)) {
                $products = $this->getRecentContextProducts($sessionId, $clientId);
            }

            if (empty($products) && $queryEmbedding !== null) {
                $products = $this->vectorSearchService->search($queryEmbedding, $intent, $clientId);
            }

            // Fallback: pure SQL filter when embedding is unavailable
            if (empty($products)) {
                $products = $this->fallbackSqlSearch($intent, $clientId, $userMessage);
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

                // ── 1b. Local greeting shortcut (avoids API misclassification) ─
                $greetingIntent = $this->detectGreetingLocally($userMessage);

                // ── 2. PARALLEL: classify+extract AND embedding ───────────────
                [$combined, $embedding] = $this->aiService->classifyExtractAndEmbed(
                    $contextualQuery,
                    $contextualQuery,
                    array_slice($history, -6)
                );

                $conversationalIntent = $greetingIntent
                    ?? ($combined['conversational_intent'] ?? 'product_search');

                Log::info('streamChat classified', [
                    'intent'  => $conversationalIntent,
                    'session' => $sessionId,
                ]);

                // Promote 'question' intent to 'product_search' when the session
                // already has products — user is asking about something they saw.
                $streamPromotedProducts = [];
                if ($conversationalIntent === 'question') {
                    $streamPromotedProducts = $this->getRecentContextProducts($sessionId, $clientId);
                    if (!empty($streamPromotedProducts)) {
                        $conversationalIntent = 'product_search';
                    }
                }

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
                // Seed with promoted products (from question→product_search) or
                // short-circuit explicit follow-up references.
                $products = $streamPromotedProducts;

                if (empty($products) && $this->isFollowUpReference($userMessage)) {
                    $products = $this->getRecentContextProducts($sessionId, $clientId);
                }

                if (empty($products) && $embedding !== null) {
                    $products = $this->vectorSearchService->search($embedding, $intent, $clientId);
                }

                if (empty($products)) {
                    $products = $this->fallbackSqlSearch($intent, $clientId, $userMessage);
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
     * Enrich a follow-up query with the ORIGINAL topic from the conversation
     * so the embedding and intent extractor have enough signal to work with.
     *
     * Key improvement over naive "last user message" approach:
     * we scan back to find the last SUBSTANTIVE (non-follow-up) user message
     * so that multi-turn follow-up chains like:
     *   Turn 1: "I need a shock absorber for Volvo"  ← topic
     *   Turn 2: "what about the price?"              ← follow-up
     *   Turn 3: "same but for IVECO"                 ← follow-up
     * always get enriched with Turn 1 context, not Turn 2.
     */
    private function buildContextualQuery(string $currentMessage, array $history): string
    {
        if (empty($history)) {
            return $currentMessage;
        }

        $msgLower = strtolower(trim($currentMessage));

        // Standalone new-search indicators — return unchanged immediately
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

        // Expanded follow-up signal phrases
        $followUpPhrases = [
            'what about', 'how about', 'and also', 'any in', 'any for',
            'instead', 'same but', 'cheaper', 'more expensive', 'similar',
            'different color', 'different size', 'in blue', 'in red',
            'in black', 'in white', 'in green', 'in yellow', 'in pink',
            'in large', 'in small', 'in xl', 'in xs', 'in xxl',
            'any other', 'can you show', 'something else',
            'what is the price', 'how much', 'its price', 'the price',
            'price of', 'cost of', 'any cheaper', 'show me more',
            'what is the sku', "its sku", 'the sku', 'available',
            'in stock', 'any alternative', 'for iveco', 'for volvo',
            'for man ', 'for daf ', 'for scania', 'for mercedes', 'for renault',
            'do you have', 'got any', 'any other brand', 'what brand',
        ];

        // Short message (<= 35 chars) is almost always a follow-up
        $isFollowUp = mb_strlen(trim($currentMessage)) <= 35;
        if (!$isFollowUp) {
            foreach ($followUpPhrases as $phrase) {
                if (str_contains($msgLower, $phrase)) {
                    $isFollowUp = true;
                    break;
                }
            }
        }

        if (!$isFollowUp) {
            return $currentMessage;
        }

        // Find the TOPIC message: last substantial user message that isn't itself a follow-up
        $topicMessage = $this->findTopicMessage($history, $followUpPhrases);

        if ($topicMessage === null || $topicMessage === $currentMessage) {
            return $currentMessage;
        }

        return "{$topicMessage}. {$currentMessage}";
    }

    /**
     * Scan backwards through history to find the last "topical" user message
     * — i.e. the most recent user turn that is NOT a pure follow-up.
     * This preserves the original search subject across multi-turn follow-ups.
     */
    private function findTopicMessage(array $history, array $followUpPhrases): ?string
    {
        $userMessages = [];
        foreach ($history as $turn) {
            if (($turn['role'] ?? '') === 'user') {
                $userMessages[] = $turn['content'];
            }
        }

        if (empty($userMessages)) {
            return null;
        }

        // Walk backwards; skip short or follow-up-looking messages
        foreach (array_reverse($userMessages) as $msg) {
            $msgL = strtolower(trim($msg));

            // Skip if very short (pure follow-up like "price?" or "ok")
            if (mb_strlen($msgL) < 12) {
                continue;
            }

            // Skip if it starts with a follow-up phrase
            $looksLikeFollowUp = false;
            foreach ($followUpPhrases as $phrase) {
                if (str_starts_with($msgL, $phrase)) {
                    $looksLikeFollowUp = true;
                    break;
                }
            }

            // Also skip single-word or very short follow-up keywords
            if (in_array($msgL, ['price', 'cost', 'sku', 'available', 'cheaper', 'more', 'details', 'ok', 'yes', 'no'], true)) {
                $looksLikeFollowUp = true;
            }

            if (!$looksLikeFollowUp) {
                return $msg;  // ← original topic found
            }
        }

        // Fallback: oldest user message
        return $userMessages[0];
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
     * Fallback search using pure SQL when no embedding is available.
     * New universal schema: core columns + cross_reference/suppliers/categories JSON + attributes JSON.
     * Search priority:
     *   0. Extract numeric/part codes from raw message directly
     *   1. SKU exact → LIKE
     *   2. cross_reference JSON → suppliers JSON
     *   3. url_key LIKE
     *   4. Broad OR: name/description LIKE + categories/suppliers JSON + attributes JSON_SEARCH
     *   5. Attribute-only: brand/category/supplier from attributes/categories JSON
     * ALWAYS scoped to client_id.
     */
    private function fallbackSqlSearch(array $intent, string $clientId, string $rawQuery = ''): array
    {
        $base = \App\Models\Product::where('client_id', $clientId)
            ->where('is_deleted', false);

        // ── 0a. Extract numeric/part codes from raw query ────────────────────
        $rawCodes = [];
        if (!empty($rawQuery)) {
            preg_match_all('/\b([A-Z0-9][A-Z0-9.\/\-]{3,})\b/i', $rawQuery, $rawMatches);
            foreach ($rawMatches[1] as $token) {
                if (! preg_match('/\d/', $token)) {
                    continue;
                }
                $rawCodes[] = strtoupper(preg_replace('/[\.\/\-]/', '', $token));
                $rawCodes[] = strtoupper($token);
            }
            $rawCodes = array_unique(array_filter($rawCodes));
        }

        // ── 0b. Extract significant words from raw query (catches typos) ─────
        // Adds words like "break" even when AI normalises to "brake", so both
        // are searched. LOWER(CAST) matching means "brake" still hits "Brake".
        $rawWords = [];
        if (!empty($rawQuery)) {
            static $stopwords = [
                'the','and','for','with','that','this','are','you','can','not',
                'want','buy','find','show','get','have','like','need','also',
                'any','all','some','give','tell','do','i','a','an','in','of',
                'to','is','it','me','my','we','be','has','was','will',
            ];
            preg_match_all('/\b([a-zA-Z]{3,})\b/', $rawQuery, $wordMatches);
            foreach ($wordMatches[1] as $word) {
                $w = strtolower($word);
                if (! in_array($w, $stopwords, true)) {
                    $rawWords[] = $w;
                }
            }
            $rawWords = array_unique($rawWords);
        }

        // ── 1. SKU: exact then LIKE (intent + raw codes) ──────────────────────
        $skuCandidates = array_filter([
            !empty($intent['sku']) ? strtoupper(trim($intent['sku'])) : null,
        ]);
        $skuCandidates = array_unique(array_merge($skuCandidates, $rawCodes));

        foreach ($skuCandidates as $candidate) {
            $hit = (clone $base)->whereRaw('UPPER(sku) = ?', [$candidate])->first();
            if (! $hit) {
                $hit = (clone $base)->whereRaw('UPPER(sku) LIKE ?', ['%' . $candidate . '%'])->first();
            }
            if ($hit) {
                return [$hit->toArray()];
            }
        }

        // ── 2. Cross-reference JSON search (intent + raw codes) ───────────────
        $crCandidates = array_unique(array_filter(array_merge(
            [$intent['cross_reference'] ?? null, $intent['sku'] ?? null],
            $rawCodes
        )));

        foreach ($crCandidates as $cr) {
            $cr      = strtolower(trim($cr));
            $crLike  = '%' . $cr . '%';
            $hits = (clone $base)->where(function ($q) use ($crLike) {
                $q->orWhereRaw('LOWER(CAST(`cross_reference` AS CHAR)) LIKE ?', [$crLike])
                  ->orWhereRaw('LOWER(CAST(`suppliers`       AS CHAR)) LIKE ?', [$crLike]);
            })->limit(5)->get();

            if ($hits->isNotEmpty()) {
                return $hits->toArray();
            }
        }

        // ── 3. url_key exact + LIKE (intent + raw codes) ─────────────────────
        foreach ($crCandidates as $code) {
            $hit = (clone $base)->where(function ($q) use ($code) {
                $q->where('url_key', $code)
                  ->orWhere('url_key', 'LIKE', '%' . $code . '%');
            })->first();

            if ($hit) {
                return [$hit->toArray()];
            }
        }

        // ── 4. Broad keyword OR search: text + JSON columns + attributes ─────
        $keywords = array_unique(array_filter(array_merge(
            array_map('strtolower', (array) ($intent['keywords'] ?? [])),
            !empty($intent['category']) ? [strtolower($intent['category'])] : [],
            !empty($intent['brand'])    ? [strtolower($intent['brand'])]    : [],
            $rawWords,  // non-code words extracted directly from raw query
            $rawCodes
        )));

        if (!empty($keywords)) {
            $broadQuery = (clone $base)->where(function ($q) use ($keywords) {
                foreach (array_slice($keywords, 0, 10) as $kw) {
                    $kw = strtolower(trim($kw));
                    if (strlen($kw) < 2) {
                        continue;
                    }
                    $like = '%' . $kw . '%';
                    // Core text columns (case-insensitive via LOWER)
                    $q->orWhereRaw('LOWER(name)              LIKE ?', [$like])
                      ->orWhereRaw('LOWER(description)       LIKE ?', [$like])
                      ->orWhereRaw('LOWER(short_description) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(sku)               LIKE ?', [$like]);
                    // JSON columns — case-insensitive via LOWER(CAST)
                    $q->orWhereRaw('LOWER(CAST(`cross_reference` AS CHAR)) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(CAST(`suppliers`       AS CHAR)) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(CAST(`categories`      AS CHAR)) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(CAST(`attributes`      AS CHAR)) LIKE ?', [$like]);
                }
            });

            $results = $broadQuery->orderByDesc('popularity')->limit(5)->get();

            if ($results->isNotEmpty()) {
                return $results->toArray();
            }
        }

        // ── 5. Attribute-only fallback (brand/category/supplier from JSON) ─────
        $hasAttribute = !empty($intent['brand']) || !empty($intent['color'])
            || !empty($intent['size'])  || !empty($intent['category']);

        if (! $hasAttribute) {
            return []; // Nothing to filter on — return empty rather than top-5 noise
        }

        $attrQuery = (clone $base);

        if (!empty($intent['brand'])) {
            $like = '%' . strtolower($intent['brand']) . '%';
            $attrQuery->where(function ($q) use ($like) {
                $q->orWhereRaw('LOWER(CAST(`attributes` AS CHAR)) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(CAST(`suppliers`   AS CHAR)) LIKE ?', [$like]);
            });
        }
        if (!empty($intent['color'])) {
            $like = '%' . strtolower($intent['color']) . '%';
            $attrQuery->whereRaw('LOWER(CAST(`attributes` AS CHAR)) LIKE ?', [$like]);
        }
        if (!empty($intent['size'])) {
            $like = '%' . strtolower($intent['size']) . '%';
            $attrQuery->whereRaw('LOWER(CAST(`attributes` AS CHAR)) LIKE ?', [$like]);
        }
        if (!empty($intent['category'])) {
            $like = '%' . strtolower($intent['category']) . '%';
            $attrQuery->where(function ($q) use ($like) {
                $q->orWhereRaw('LOWER(CAST(`categories`  AS CHAR)) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(CAST(`attributes`  AS CHAR)) LIKE ?', [$like]);
            });
        }

        return $attrQuery->orderByDesc('popularity')->limit(5)->get()->toArray();
    }

    /**
     * Detect follow-up questions that should REUSE the last shown products
     * (i.e. user is asking metadata ABOUT prior results, not searching for new ones).
     *
     * Returns true ONLY when the user wants a property of a product already shown:
     *   price, SKU, weight, availability, specs, brand of the same item.
     *
     * Returns false for "find different products" follow-ups like:
     *   "same but for IVECO", "any cheaper?", "show me more", "any alternatives?"
     *   — these should do a new enriched search, not reuse old results.
     */
    private function isFollowUpReference(string $message): bool
    {
        $msgL = strtolower(trim($message));

        // Explicit metadata patterns (about the same previously shown product)
        $patterns = [
            '/\b(above|previous|last|that|same)\s*(product|part|item|one)\b/i',
            '/\b(sku|price|cost|weight|dimension|spec|description|detail|info)\s*(of|for)?\s*(above|that|previous|it|this|the\s+part|the\s+product)\b/i',
            '/\bwhat\s+(is|are)\s+(the|its|their)\s+(sku|price|cost|weight|brand|name|detail|description)\b/i',
            '/\bits\s+(sku|price|cost|weight|name|description|detail)\b/i',
            '/\bmore\s+(detail|info|about)\s+(that|the\s+above|previous|it|this)\b/i',
            '/\bshow\s+me\s+more\s+about\s+(it|that|this|above)\b/i',
            '/^(what about|how about)\s+(the\s+)?(price|cost|sku|weight)\b/i',
            '/^(price|cost|how much|sku)\??$/i',
            '/\bhow\s+much\s+(is|does|for)\s+(it|that|this|the\s+above)\b/i',
            '/\bwhat.*(sku|part\s*number)\b/i',
            '/\bis\s+(it|this|that)\s+(available|in\s+stock)\b/i',
            '/\bstock\s+status\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch the most recently shown products for a session from the DB.
     * Uses the product IDs stored in the last assistant turn that had products.
     */
    private function getRecentContextProducts(string $sessionId, string $clientId): array
    {
        $lastTurn = Conversation::where('session_id', $sessionId)
            ->where('role', 'assistant')
            ->whereNotNull('products')
            ->latest()
            ->first();

        if (! $lastTurn || empty($lastTurn->products)) {
            return [];
        }

        $ids = array_filter(array_column($lastTurn->products, 'id'));
        if (empty($ids)) {
            return [];
        }

        return \App\Models\Product::where('client_id', $clientId)
            ->whereIn('id', $ids)
            ->get()
            ->toArray();
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
     * Detect non-shopping intents locally before calling the API.
     * Returns an intent string if matched, null to let the API decide.
     */
    private function detectGreetingLocally(string $message): ?string
    {
        $clean = strtolower(trim(preg_replace('/[^a-z\s\']/i', ' ', $message)));
        $clean = preg_replace('/\s+/', ' ', $clean);
        $words = array_filter(explode(' ', $clean));

        // ── Greetings ────────────────────────────────────────────────────────
        $greetings = [
            'hi', 'hello', 'hey', 'salam', 'howdy', 'greetings',
            'good morning', 'good evening', 'good afternoon', 'good day',
            'hi there', 'hey there', 'hello there', 'whats up', 'what\'s up',
            'assalam', 'assalamualaikum', 'salaam', 'yo', 'sup',
        ];
        if (in_array($clean, $greetings, true)) {
            return 'greeting';
        }

        // ── If message contains shopping signals, let API classify ────────────
        $shoppingSignals = [
            // Clothing / fashion
            'buy', 'shirt', 'hoodie', 'jacket', 'jeans', 'pants', 'shoes',
            'sneakers', 'clothes', 'clothing', 'outfit', 'wear', 'dress',
            'suit', 'blazer', 'coat', 'hat', 'cap', 'bag', 'wallet', 'belt',
            'size', 'color', 'colour', 'price', 'cheap', 'brand', 'fashion',
            'casual', 'formal', 'style', 'fabric', 'material', 'scarf',
            'recommend', 'suggestion', 'find me', 'looking for', 'want a',
            'need a', 'budget', 'affordable', 'expensive',
            // Automotive / industrial parts
            'shock', 'absorber', 'brake', 'brakes', 'chamber', 'suspension',
            'air dryer', 'valve', 'filter', 'compressor', 'caliper', 'clutch',
            'volvo', 'iveco', 'scania', 'mercedes', 'man ', 'daf ', 'renault',
            'wabco', 'haldex', 'meritor', 'sachs', 'knorr', 'bosch',
            'sku', 'part', 'parts', 'cross reference', 'oem', 'supplier',
            'truck', 'trailer', 'axle', 'wheel', 'bearing', 'seal',
            // Generic shopping follow-ups that should go to API
            'what about', 'how about', 'any cheaper', 'same but', 'show more',
            'in stock', 'available', 'alternative', 'similar',
        ];
        foreach ($shoppingSignals as $signal) {
            if (str_contains($clean, $signal)) {
                return null;
            }
        }

        // ── Clearly unrelated topics → redirect politely ──────────────────────
        $unrelatedSignals = [
            'cook', 'cooking', 'recipe', 'food', 'biryani', 'pizza', 'burger',
            'weather', 'temperature', 'rain', 'sunny', 'forecast',
            'football', 'cricket', 'sports', 'game', 'match', 'score',
            'movie', 'film', 'song', 'music', 'news', 'politics',
            'code', 'programming', 'software', 'math', 'science',
            'joke', 'funny', 'laugh', 'how are you', 'how r you',
            'what is your name', 'who are you', 'what can you do',
        ];
        foreach ($unrelatedSignals as $signal) {
            if (str_contains($clean, $signal)) {
                return 'unrelated';
            }
        }

        return null;
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
