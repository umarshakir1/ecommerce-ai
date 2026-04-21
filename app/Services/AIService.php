<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AIService
 *
 * Handles all OpenRouter API interactions:
 *  1. Generating text embeddings for products
 *  2. Extracting structured intent/entities from user queries
 *  3. Generating conversational RAG responses
 */
class AIService
{
    private string $apiKey;
    private string $baseUrl;
    private string $chatModel;
    private string $embeddingModel;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey         = config('openrouter.api_key');
        $this->baseUrl        = rtrim(config('openrouter.base_url'), '/');
        $this->chatModel      = config('openrouter.chat_model');
        $this->embeddingModel = config('openrouter.embedding_model');
        $this->timeout        = config('openrouter.timeout', 60);
    }

    // -------------------------------------------------------------------------
    // EMBEDDING GENERATION
    // -------------------------------------------------------------------------

    /**
     * Generate a text embedding vector for the given input string.
     * Uses OpenRouter's embedding endpoint (OpenAI-compatible).
     *
     * @param  string  $text  The text to embed
     * @return float[]|null   Embedding vector or null on failure
     */
    public function generateEmbedding(string $text): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->buildHeaders())
                ->post("{$this->baseUrl}/embeddings", [
                    'model' => $this->embeddingModel,
                    'input' => $text,
                ]);

            if ($response->failed()) {
                Log::error('AIService::generateEmbedding failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();

            // OpenAI-compatible response: data[0].embedding
            return $data['data'][0]['embedding'] ?? null;

        } catch (\Throwable $e) {
            Log::error('AIService::generateEmbedding exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // INTENT & ENTITY EXTRACTION
    // -------------------------------------------------------------------------

    /**
     * Parse a natural-language user query into a structured attribute object.
     *
     * Returns an associative array like:
     * {
     *   "age": 21,
     *   "color": "black",
     *   "size": "L",
     *   "category": "clothing",
     *   "gender": "male",
     *   "intent": "buy casual outfit",
     *   "price_range": { "min": null, "max": null },
     *   "keywords": ["hoodie", "casual"]
     * }
     *
     * @param  string  $query  Raw user message
     * @return array           Structured intent data (empty array on failure)
     */
    public function extractIntent(string $query): array
    {
        $systemPrompt = <<<'PROMPT'
You are an eCommerce intent extraction engine.
Given a user shopping query, extract structured attributes as valid JSON.

Return ONLY a raw JSON object (no markdown, no explanation) with these keys:
- "sku": string or null — extract if the user mentions a specific product code or SKU. SKUs are alphanumeric codes that may contain hyphens, e.g. "HDY-001", "TSH-002", "DEMO-030", "SKU-100", "ABC123", "PROD-001". They are uppercase or mixed-case identifiers that do NOT look like normal English words. If present, return it uppercased with no surrounding quotes.
- "brand": string or null — extract if the user mentions a brand name (e.g. "Nike", "Adidas", "Zara").
- "age": integer or null
- "color": string or null (e.g. "black", "red")
- "size": string or null (e.g. "S", "M", "L", "XL", "XXL", or numeric shoe sizes like "42", "44")
- "category": string or null — use ONLY one of: "clothing", "shoes", "accessories", "electronics", "home", "sports", "beauty", "formal", "casual". For wireless, headphones, speakers, trackers, laptops, keyboards — always use "electronics". For bags, wallets, sunglasses — use "accessories". If truly unclear, return null.
- "gender": string or null ("male", "female", "unisex")
- "intent": string summarizing what the user wants (max 10 words)
- "price_range": object with "min" (float or null) and "max" (float or null)
- "keywords": array of relevant product search keywords (max 6 items)
PROMPT;

        try {
            $response = $this->chatCompletion([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $query],
            ], temperature: 0.1, maxTokens: 300);

            // Strip potential markdown code fences before decoding
            $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($response));
            $clean = preg_replace('/\s*```$/', '', $clean);

            $decoded = json_decode($clean, true);

            return is_array($decoded) ? $decoded : [];

        } catch (\Throwable $e) {
            Log::error('AIService::extractIntent exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // RAG RESPONSE GENERATION
    // -------------------------------------------------------------------------

    /**
     * Generate a conversational reply for non-shopping intents
     * (greeting, casual, unrelated, question).
     * No product context is passed — this is purely conversational.
     *
     * @param  string  $userMessage          The raw user message
     * @param  string  $intent               Detected intent label
     * @param  array   $conversationHistory  Previous turns
     * @return string                        AI-generated reply
     */
    public function generateConversationalResponse(
        string $userMessage,
        string $intent,
        array $conversationHistory = []
    ): string {
        $systemPrompt = <<<'PROMPT'
You are an intelligent and friendly eCommerce shopping assistant named ShopAI.

Rules:
1. PRICE/DETAIL FOLLOW-UPS: If the user asks about the price, availability, or details of a product that was mentioned earlier in the conversation history, answer DIRECTLY with the specific information from the conversation. For example, if the conversation showed "Polarised Sunglasses — $39.99" and the user asks "what is the price of polarised", reply: "The Polarised Sunglasses are priced at $39.99."
2. SHORT FOLLOW-UPS: If the user just says "price", "how much", or similar, look at what was discussed most recently and give the price(s) of those products.
3. Do NOT say you don't have access to pricing — you do, it is in the conversation history.
4. If the user greets you, respond warmly and naturally.
5. For completely unrelated questions (jokes, weather), politely redirect to shopping.
6. Keep replies concise — under 80 words.
7. Never say "As an AI" — speak like a knowledgeable human sales assistant.
PROMPT;

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach (array_slice($conversationHistory, -8) as $turn) {
            $messages[] = $turn;
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            return $this->chatCompletion($messages, temperature: 0.75, maxTokens: 200);
        } catch (\Throwable $e) {
            Log::error('AIService::generateConversationalResponse exception', ['error' => $e->getMessage()]);
            return "Hey there! I'm here to help you find great products. What are you looking for today?";
        }
    }

    public function generateRAGResponse(
        string $userQuery,
        array $products,
        array $conversationHistory = []
    ): string {
        $systemPrompt = <<<'PROMPT'
You are an intelligent and friendly eCommerce shopping assistant named ShopAI.

ABSOLUTE RULES — follow these exactly:
1. LIVE INVENTORY: The "--- PRODUCTS FOUND ---" section below is a LIVE search result showing products that ARE in stock right now. These products EXIST. Never say a product is unavailable if it appears in that section.
2. NEVER CONTRADICT THE PRODUCT LIST: If a product is in "--- PRODUCTS FOUND ---", you MUST recommend it when relevant. Do NOT say "I don't have X" if X is listed there.
3. BASE RECOMMENDATIONS ON CURRENT RESULTS: Recommend ONLY from the current "--- PRODUCTS FOUND ---" section. Do NOT recommend products mentioned in previous conversation turns.
4. PRICE: Always state the exact price from the product data as "$X.XX". For price queries, answer directly.
5. SHORT FOLLOW-UPS: If the user says "price", "how much", or similar short follow-ups, answer using the most recently discussed products.
6. NO PERFECT MATCH: If the product list is empty or genuinely irrelevant, say so and suggest trying different search terms.
7. Keep responses under 120 words. Be natural, not robotic. Never say "As an AI".
PROMPT;

        // Build a concise product context block
        $productContext = $this->formatProductsForPrompt($products);

        // Build message history (last 6 turns for context, not product history)
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach (array_slice($conversationHistory, -6) as $turn) {
            $messages[] = $turn;
        }

        $messages[] = [
            'role'    => 'user',
            'content' => "My request: {$userQuery}\n\n--- PRODUCTS FOUND (live inventory — these ARE available) ---\n{$productContext}\n---\n\nRecommend the most relevant products from the list above and explain why they match my request.",
        ];

        try {
            return $this->chatCompletion($messages, temperature: 0.7, maxTokens: 500);
        } catch (\Throwable $e) {
            Log::error('AIService::generateRAGResponse exception', ['error' => $e->getMessage()]);
            return 'I apologize, I encountered an error while generating recommendations. Please try again.';
        }
    }

    // -------------------------------------------------------------------------
    // PARALLEL CLASSIFY + EMBED  (replaces two sequential calls)
    // -------------------------------------------------------------------------

    /**
     * Run intent classification/extraction AND embedding generation simultaneously
     * via Laravel Http::pool().  Replaces the old sequential classify() → extractIntent()
     * → generateEmbedding() chain with a single parallel round-trip.
     *
     * @param  string  $query          Contextual user query (may include prior context)
     * @param  string  $embeddingText  Text to embed (usually same as $query)
     * @param  array   $recentHistory  Last N conversation turns for classification context
     * @return array   [ classifyResult (array), embedding (float[]|null) ]
     */
    public function classifyExtractAndEmbed(
        string $query,
        string $embeddingText,
        array  $recentHistory = []
    ): array {
        $systemPrompt = <<<'PROMPT'
You are an eCommerce assistant analyzer. Given a user message (with optional conversation context), return ONLY a raw JSON object with these exact fields:
- "conversational_intent": one of "greeting","product_search","recommendation","question","casual","unrelated"
- "sku": string or null — extract if the user mentions a specific product code or SKU. SKUs are alphanumeric codes that may contain hyphens, e.g. "HDY-001", "TSH-002", "DEMO-030", "SKU-100", "ABC123", "PROD-001". They are uppercase or mixed-case identifiers that do NOT look like normal English words. If present, return it uppercased with no surrounding quotes.
- "brand": string or null — extract if the user mentions a brand name (e.g. "Nike", "Adidas", "Zara").
- "age": integer or null
- "color": string or null
- "size": string or null (e.g. "S", "M", "L", "XL", "XXL", or numeric shoe sizes like "42", "44")
- "category": string or null — one of: "clothing","shoes","accessories","electronics","home","sports","beauty","formal","casual", or null. For headphones/speakers/laptops → "electronics". For bags/wallets/sunglasses → "accessories". If unclear → null.
- "gender": string or null ("male","female","unisex")
- "intent": string summarizing what the user wants (max 10 words)
- "price_range": {"min": float or null, "max": float or null}
- "keywords": array of relevant keywords (max 6)

Rules for conversational_intent:
- History shows products + follow-up message ("what about in white?", "how much?") → "product_search"
- Greetings/hello/hi/salam → "greeting"
- Small talk/jokes → "casual"
- Off-topic (weather, coding) → "unrelated"
- Explicit product requests → "product_search"
- Asking for suggestions → "recommendation"
PROMPT;

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach (array_slice($recentHistory, -4) as $turn) {
            $messages[] = $turn;
        }
        $messages[] = ['role' => 'user', 'content' => $query];

        $headers  = $this->buildHeaders();
        $baseUrl  = $this->baseUrl;
        $chatMod  = $this->chatModel;
        $embedMod = $this->embeddingModel;
        $timeout  = $this->timeout;

        try {
            $responses = Http::pool(fn ($pool) => [
                'classify' => $pool->timeout($timeout)
                    ->withHeaders($headers)
                    ->post("{$baseUrl}/chat/completions", [
                        'model'       => $chatMod,
                        'messages'    => $messages,
                        'temperature' => 0.05,
                        'max_tokens'  => 220,
                    ]),
                'embed' => $pool->timeout($timeout)
                    ->withHeaders($headers)
                    ->post("{$baseUrl}/embeddings", [
                        'model' => $embedMod,
                        'input' => $embeddingText,
                    ]),
            ]);

            // ── Parse classify result ───────────────────────────────────────
            $raw   = $responses['classify']->json('choices.0.message.content', '{}');
            $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
            $clean = preg_replace('/\s*```$/', '', $clean);
            $classifyResult = json_decode($clean, true);

            if (!is_array($classifyResult)) {
                $classifyResult = [];
            }

            $validIntents = ['greeting', 'product_search', 'recommendation', 'question', 'casual', 'unrelated'];
            if (!in_array($classifyResult['conversational_intent'] ?? '', $validIntents, true)) {
                $classifyResult['conversational_intent'] = 'product_search';
            }

            // ── Parse embedding ─────────────────────────────────────────────
            $embedding = $responses['embed']->json('data.0.embedding');

            return [$classifyResult, is_array($embedding) ? $embedding : null];

        } catch (\Throwable $e) {
            Log::error('AIService::classifyExtractAndEmbed exception', ['error' => $e->getMessage()]);
            return [['conversational_intent' => 'product_search'], null];
        }
    }

    // -------------------------------------------------------------------------
    // STREAMING RESPONSES
    // -------------------------------------------------------------------------

    /**
     * Stream a RAG product-search response token-by-token.
     * Yields string tokens as they arrive from the API.
     *
     * @return \Generator<string>
     */
    public function generateRAGResponseStream(
        string $userQuery,
        array  $products,
        array  $conversationHistory = []
    ): \Generator {
        $systemPrompt = <<<'PROMPT'
You are an intelligent and friendly eCommerce shopping assistant named ShopAI.

ABSOLUTE RULES:
1. LIVE INVENTORY: Products in "--- PRODUCTS FOUND ---" ARE in stock. Never say unavailable if listed.
2. NEVER CONTRADICT THE PRODUCT LIST.
3. BASE RECOMMENDATIONS ON CURRENT RESULTS ONLY.
4. PRICE: Always state exact price as "$X.XX".
5. SHORT FOLLOW-UPS: If user says "price" or "how much", answer from most recently discussed products.
6. NO PERFECT MATCH: If product list is empty, say so and suggest different terms.
7. Keep responses under 120 words. Be natural. Never say "As an AI".
PROMPT;

        $productContext = $this->formatProductsForPrompt($products);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach (array_slice($conversationHistory, -6) as $turn) {
            $messages[] = $turn;
        }
        $messages[] = [
            'role'    => 'user',
            'content' => "My request: {$userQuery}\n\n--- PRODUCTS FOUND (live inventory) ---\n{$productContext}\n---\n\nRecommend the most relevant products from the list above.",
        ];

        yield from $this->chatCompletionStream($messages, 0.7, 400);
    }

    /**
     * Stream a conversational (non-shopping) response token-by-token.
     *
     * @return \Generator<string>
     */
    public function generateConversationalResponseStream(
        string $userMessage,
        string $intent,
        array  $conversationHistory = []
    ): \Generator {
        $systemPrompt = <<<'PROMPT'
You are an intelligent and friendly eCommerce shopping assistant named ShopAI.

Rules:
1. PRICE/DETAIL FOLLOW-UPS: Answer directly using conversation history pricing data.
2. SHORT FOLLOW-UPS: If user says "price" or "how much", give price(s) of most recently discussed products.
3. Do NOT say you don't have access to pricing — you do, it's in the conversation history.
4. If greeted, respond warmly and naturally.
5. For completely unrelated questions, politely redirect to shopping.
6. Keep replies concise — under 80 words.
7. Never say "As an AI" — speak like a knowledgeable human sales assistant.
PROMPT;

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach (array_slice($conversationHistory, -8) as $turn) {
            $messages[] = $turn;
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        yield from $this->chatCompletionStream($messages, 0.75, 180);
    }

    /**
     * Internal: stream a chat completion from OpenRouter via SSE.
     * Yields individual content tokens as strings.
     *
     * @return \Generator<string>
     */
    private function chatCompletionStream(
        array $messages,
        float $temperature = 0.7,
        int   $maxTokens   = 400
    ): \Generator {
        $response = Http::timeout($this->timeout)
            ->withOptions(['stream' => true])
            ->withHeaders($this->buildHeaders())
            ->post("{$this->baseUrl}/chat/completions", [
                'model'       => $this->chatModel,
                'messages'    => $messages,
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens,
                'stream'      => true,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "OpenRouter streaming error {$response->status()}: {$response->body()}"
            );
        }

        $body   = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $chunk  = $body->read(256);
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line   = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line   = trim($line);

                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = substr($line, 6);

                if ($data === '[DONE]') {
                    return;
                }

                $parsed = json_decode($data, true);
                $token  = $parsed['choices'][0]['delta']['content'] ?? null;

                if ($token !== null) {
                    yield $token;
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------------------------

    /**
     * Send a chat completion request to OpenRouter.
     */
    private function chatCompletion(
        array $messages,
        float $temperature = 0.7,
        int $maxTokens = 500
    ): string {
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->buildHeaders())
            ->post("{$this->baseUrl}/chat/completions", [
                'model'       => $this->chatModel,
                'messages'    => $messages,
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens,
            ]);

        if ($response->failed()) {
            Log::error('AIService::chatCompletion failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException(
                "OpenRouter API error {$response->status()}: {$response->body()}"
            );
        }

        $data = $response->json();

        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Format product list into a readable string for the AI prompt.
     */
    private function formatProductsForPrompt(array $products): string
    {
        if (empty($products)) {
            return 'No matching products found.';
        }

        $lines = [];
        foreach ($products as $i => $product) {
            $num    = $i + 1;
            $name   = $product['name']        ?? 'N/A';
            $brand  = !empty($product['brand'])    ? ' by ' . $product['brand'] : '';
            $sku    = !empty($product['sku'])       ? ' [SKU: ' . $product['sku'] . ']' : '';
            $cat    = $product['category']    ?? 'N/A';
            $color  = $product['color']       ?? 'N/A';
            $size   = $product['size']        ?? 'N/A';
            $price  = isset($product['price']) ? '$' . number_format($product['price'], 2) : 'N/A';
            $desc   = $product['description'] ?? '';
            $score  = isset($product['similarity_score'])
                ? ' [match: ' . round($product['similarity_score'] * 100, 1) . '%]'
                : '';

            // Show available variant info so the AI can inform the user
            $availSizes  = !empty($product['available_sizes'])  ? "\n   Available sizes: "  . implode(', ', (array) $product['available_sizes'])  : '';
            $availColors = !empty($product['available_colors']) ? "\n   Available colors: " . implode(', ', (array) $product['available_colors']) : '';

            $lines[] = "{$num}. {$name}{$brand}{$sku} ({$cat}) - Color: {$color}, Size: {$size}, Price: {$price}{$score}\n   {$desc}{$availSizes}{$availColors}";
        }

        return implode("\n\n", $lines);
    }

    /**
     * Build standard HTTP headers for all OpenRouter requests.
     */
    private function buildHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type'  => 'application/json',
            'HTTP-Referer'  => config('openrouter.site_url'),
            'X-Title'       => config('openrouter.site_name'),
        ];
    }
}
