<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IntentService
 *
 * Classifies a user message into one of the following intents before
 * the RAG pipeline is invoked.  This allows the ChatController to
 * skip product search entirely for greetings, small talk, and
 * off-topic messages, making the assistant feel far more human.
 *
 * Possible intent values
 * ──────────────────────
 *  greeting       – hi, hello, salam, good morning …
 *  product_search – explicit product request
 *  recommendation – asking for suggestions / recommendations
 *  question       – general shopping-related question (fit, care, sizing …)
 *  casual         – small talk, jokes, how-are-you …
 *  unrelated      – completely off-topic (weather, politics, coding …)
 */
class IntentService
{
    private const VALID_INTENTS = [
        'greeting',
        'product_search',
        'recommendation',
        'question',
        'casual',
        'unrelated',
    ];

    /** Intents that should trigger the full RAG pipeline */
    public const SHOPPING_INTENTS = ['product_search', 'recommendation'];

    private string $apiKey;
    private string $baseUrl;
    private string $chatModel;
    private int    $timeout;

    public function __construct()
    {
        $this->apiKey    = config('openrouter.api_key');
        $this->baseUrl   = rtrim(config('openrouter.base_url'), '/');
        $this->chatModel = config('openrouter.chat_model');
        $this->timeout   = config('openrouter.timeout', 60);
    }

    // -------------------------------------------------------------------------
    // PUBLIC API
    // -------------------------------------------------------------------------

    /**
     * Classify the user message and return one of the VALID_INTENTS strings.
     * Accepts optional recent conversation history so follow-up messages
     * ("what about in blue?", "show me cheaper ones") are classified
     * correctly in context rather than in isolation.
     *
     * Falls back to 'product_search' on failure so the RAG pipeline still runs.
     *
     * @param  string  $message        Raw user input
     * @param  array   $recentHistory  Last N turns as [['role'=>…,'content'=>…]]
     * @return string                  One of the VALID_INTENTS values
     */
    public function classify(string $message, array $recentHistory = []): string
    {
        $systemPrompt = <<<'PROMPT'
You are an intent classification engine for an eCommerce AI assistant.

Your job is to analyze the user message — considering any prior conversation context — and return ONLY a JSON response with one field: "intent".

Possible intents:
- "greeting"        → hi, hello, salam, good morning, hey, howdy
- "product_search"  → specific product request (e.g. "I want a black hoodie size L")
- "recommendation"  → asking for suggestions (e.g. "what should I wear for summer?")
- "question"        → general shopping-related question (sizing, material, care, returns)
- "casual"          → small talk, jokes, how are you, tell me something fun
- "unrelated"       → completely off-topic (weather, coding, politics, math)

IMPORTANT: If the conversation history shows a product discussion and the new message is a follow-up
(e.g. "what about in white?", "show me cheaper ones", "any in size L?"), classify it as "product_search".

Return format (raw JSON only, no markdown, no explanation):
{"intent": "intent_name"}
PROMPT;

        // Build the messages array: system + last 2 history turns (for context) + current message
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach (array_slice($recentHistory, -4) as $turn) {
            $messages[] = $turn;
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->buildHeaders())
                ->post("{$this->baseUrl}/chat/completions", [
                    'model'       => $this->chatModel,
                    'messages'    => $messages,
                    'temperature' => 0.0,
                    'max_tokens'  => 30,
                ]);

            if ($response->failed()) {
                Log::warning('IntentService::classify API failure', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return 'product_search'; // safe fallback
            }

            $content = $response->json('choices.0.message.content', '');
            $intent  = $this->parseIntent($content);

            Log::info('IntentService::classify', [
                'message' => $message,
                'intent'  => $intent,
            ]);

            return $intent;

        } catch (\Throwable $e) {
            Log::error('IntentService::classify exception', ['error' => $e->getMessage()]);
            return 'product_search'; // safe fallback
        }
    }

    /**
     * Return true when the intent should trigger the full RAG pipeline.
     */
    public function isShopping(string $intent): bool
    {
        return in_array($intent, self::SHOPPING_INTENTS, true);
    }

    // -------------------------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------------------------

    /**
     * Extract a valid intent string from the raw API content.
     * Strips markdown fences, decodes JSON, validates against whitelist.
     */
    private function parseIntent(string $content): string
    {
        $clean   = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $clean   = preg_replace('/\s*```$/', '', $clean);
        $decoded = json_decode($clean, true);

        $intent = $decoded['intent'] ?? '';

        if (in_array($intent, self::VALID_INTENTS, true)) {
            return $intent;
        }

        // Fuzzy fallback: check if any valid intent appears literally in the response
        foreach (self::VALID_INTENTS as $valid) {
            if (stripos($content, $valid) !== false) {
                return $valid;
            }
        }

        return 'product_search';
    }

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
