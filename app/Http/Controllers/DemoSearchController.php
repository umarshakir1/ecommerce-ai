<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DemoSearchController
 *
 * GET /api/demo/search?q={term}&field={optional}&page={n}
 *
 * AI-powered multi-field product search across the full product catalogue.
 * When no `field` is given, all searchable fields are queried simultaneously
 * with type-appropriate match strategies (LIKE, JSON_CONTAINS, exact).
 */
class DemoSearchController extends Controller
{
    private const PER_PAGE      = 20;
    private const DEMO_CLIENT   = '00000000-0000-0000-0000-000000000001';

    // Fields that use standard LIKE matching
    private const LIKE_FIELDS = [
        'name', 'description', 'short_description', 'notes', 'synonym',
        'categories', 'product_groups', 'store_model', 'sub_range', 'brand',
        'category', 'color', 'conind',
    ];

    // Fields that use exact + LIKE matching
    private const EXACT_FIELDS = ['sku', 'commodity_code', 'url_key'];

    // JSON array fields: searched with JSON_CONTAINS
    private const JSON_ARRAY_FIELDS = [
        'cross_reference', 'cross_reference_syn',
        'supplier', 'supplier_v2',
        'related_skus', 'crosssell_skus', 'upsell_skus',
    ];

    public function __construct(private readonly AIService $aiService) {}

    public function search(Request $request): JsonResponse
    {
        $q     = trim($request->query('q', ''));
        $field = trim($request->query('field', ''));
        $page  = max(1, (int) $request->query('page', 1));

        if ($q === '') {
            return response()->json(['error' => 'Query parameter `q` is required.'], 422);
        }

        try {
            // ── 1. AI intent extraction ───────────────────────────────────────
            $intent  = $this->extractSearchIntent($q);
            $summary = $intent['summary'] ?? null;

            Log::info('DemoSearch intent', ['q' => $q, 'intent' => $intent]);

            // ── 2. Build and execute query ────────────────────────────────────
            $query = DB::table('products')
                ->where('client_id', self::DEMO_CLIENT)
                ->where('is_deleted', 0);

            if ($field !== '') {
                $this->applyFieldSearch($query, $field, $q);
            } else {
                $this->applyFullSearch($query, $q, $intent);
            }

            // ── 3. Count + paginate ───────────────────────────────────────────
            $total   = $query->count();
            $offset  = ($page - 1) * self::PER_PAGE;
            $results = (clone $query)
                ->select($this->selectColumns())
                ->orderByRaw('popularity DESC, id ASC')
                ->offset($offset)
                ->limit(self::PER_PAGE)
                ->get();

            // Decode JSON columns for each result
            $items = $results->map(fn ($row) => $this->decodeJsonColumns((array) $row))->values();

            // ── 4. AI summary of results ──────────────────────────────────────
            $aiReply = $this->generateSearchSummary($q, $intent, $items->toArray(), $total);

            return response()->json([
                'query'      => $q,
                'field'      => $field ?: null,
                'intent'     => $intent,
                'ai_reply'   => $aiReply,
                'total'      => $total,
                'page'       => $page,
                'per_page'   => self::PER_PAGE,
                'last_page'  => (int) ceil($total / self::PER_PAGE),
                'products'   => $items,
            ]);

        } catch (\Throwable $e) {
            Log::error('DemoSearchController::search error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Search failed. Please try again.'], 500);
        }
    }

    // ── Intent extraction ──────────────────────────────────────────────────────

    private function extractSearchIntent(string $query): array
    {
        $systemPrompt = <<<'PROMPT'
You are a search intent extraction engine for an industrial/automotive parts catalogue.
Given a user search query, extract structured search attributes as valid JSON.

Return ONLY a raw JSON object (no markdown, no explanation) with these keys:
- "keywords": array of key search terms (max 6)
- "sku": string or null — if the query looks like a specific part number or SKU code
- "cross_reference": string or null — if the query contains a cross-reference/OEM number (numeric or alphanumeric code)
- "supplier": string or null — brand or supplier name mentioned
- "category": string or null — product category or type mentioned
- "commodity_code": string or null — commodity code if mentioned
- "summary": string — a short human-readable description of what the user is searching for (max 15 words)
PROMPT;

        try {
            $response = $this->aiService->extractIntent($query);

            // extractIntent returns the clothing-oriented intent; supplement with parts-specific fields
            $partsIntent = [];

            // Try a dedicated extraction call via the AIService internal method
            // by passing a parts-specific context
            $full = $this->callPartsIntentExtraction($systemPrompt, $query);

            return array_merge(
                ['keywords' => $response['keywords'] ?? []],
                $full
            );
        } catch (\Throwable $e) {
            Log::warning('DemoSearch: intent extraction failed', ['error' => $e->getMessage()]);
            return ['keywords' => explode(' ', $query), 'summary' => $query];
        }
    }

    private function callPartsIntentExtraction(string $systemPrompt, string $query): array
    {
        // Use Laravel HTTP client directly since AIService.extractIntent is clothing-oriented
        $apiKey  = config('openrouter.api_key');
        $baseUrl = rtrim(config('openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
        $model   = config('openrouter.chat_model', 'openai/gpt-4o-mini');

        if (empty($apiKey)) {
            return ['keywords' => explode(' ', $query), 'summary' => $query];
        }

        $response = \Illuminate\Support\Facades\Http::timeout(30)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => config('app.url'),
                'X-Title'       => config('app.name'),
            ])
            ->post("{$baseUrl}/chat/completions", [
                'model'       => $model,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $query],
                ],
                'temperature' => 0.1,
                'max_tokens'  => 300,
            ]);

        if ($response->failed()) {
            return ['keywords' => explode(' ', $query), 'summary' => $query];
        }

        $raw   = $response->json('choices.0.message.content', '{}');
        $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $clean = preg_replace('/\s*```$/', '', $clean);
        $data  = json_decode($clean, true);

        return is_array($data) ? $data : ['keywords' => explode(' ', $query), 'summary' => $query];
    }

    // ── Query builders ─────────────────────────────────────────────────────────

    private function applyFullSearch(\Illuminate\Database\Query\Builder $query, string $term, array $intent): void
    {
        $query->where(function ($q) use ($term, $intent) {

            // ── Exact/LIKE fields ─────────────────────────────────────────────
            foreach (self::EXACT_FIELDS as $col) {
                $q->orWhere($col, $term)
                  ->orWhere($col, 'LIKE', '%' . $term . '%');
            }

            foreach (self::LIKE_FIELDS as $col) {
                $q->orWhere($col, 'LIKE', '%' . $term . '%');
            }

            // ── JSON array fields ─────────────────────────────────────────────
            foreach (self::JSON_ARRAY_FIELDS as $col) {
                $q->orWhereRaw(
                    "JSON_SEARCH(`{$col}`, 'one', ?) IS NOT NULL",
                    [$term]
                );
                // Also try partial match inside JSON values
                $q->orWhereRaw(
                    "JSON_SEARCH(`{$col}`, 'one', ?) IS NOT NULL",
                    ['%' . $term . '%']
                );
            }

            // ── additional_attributes JSON object (keys + values) ─────────────
            $q->orWhereRaw("JSON_SEARCH(`additional_attributes`, 'one', ?) IS NOT NULL", ['%' . $term . '%']);

            // ── AI-extracted cross_reference number ───────────────────────────
            if (! empty($intent['cross_reference'])) {
                $cr = $intent['cross_reference'];
                $q->orWhereRaw("JSON_SEARCH(`cross_reference`, 'one', ?) IS NOT NULL", [$cr])
                  ->orWhereRaw("JSON_SEARCH(`cross_reference_syn`, 'one', ?) IS NOT NULL", [$cr]);
            }

            // ── AI-extracted supplier ─────────────────────────────────────────
            if (! empty($intent['supplier'])) {
                $sup = '%' . $intent['supplier'] . '%';
                $q->orWhereRaw("JSON_SEARCH(`supplier`, 'one', ?) IS NOT NULL", [$sup])
                  ->orWhereRaw("JSON_SEARCH(`supplier_v2`, 'one', ?) IS NOT NULL", [$sup]);
            }

            // ── Keyword expansion ─────────────────────────────────────────────
            if (! empty($intent['keywords'])) {
                foreach (array_slice($intent['keywords'], 0, 4) as $kw) {
                    $kw = trim($kw);
                    if (strlen($kw) < 2) {
                        continue;
                    }
                    $q->orWhere('name', 'LIKE', '%' . $kw . '%')
                      ->orWhere('description', 'LIKE', '%' . $kw . '%')
                      ->orWhere('categories', 'LIKE', '%' . $kw . '%');
                }
            }
        });
    }

    private function applyFieldSearch(\Illuminate\Database\Query\Builder $query, string $field, string $term): void
    {
        if (in_array($field, self::JSON_ARRAY_FIELDS, true)) {
            $query->where(function ($q) use ($field, $term) {
                $q->orWhereRaw("JSON_SEARCH(`{$field}`, 'one', ?) IS NOT NULL", [$term])
                  ->orWhereRaw("JSON_SEARCH(`{$field}`, 'one', ?) IS NOT NULL", ['%' . $term . '%']);
            });

        } elseif ($field === 'additional_attributes') {
            $query->whereRaw("JSON_SEARCH(`additional_attributes`, 'one', ?) IS NOT NULL", ['%' . $term . '%']);

        } elseif (in_array($field, self::EXACT_FIELDS, true)) {
            $query->where(function ($q) use ($field, $term) {
                $q->where($field, $term)
                  ->orWhere($field, 'LIKE', '%' . $term . '%');
            });

        } else {
            $query->where($field, 'LIKE', '%' . $term . '%');
        }
    }

    // ── AI result summary ──────────────────────────────────────────────────────

    private function generateSearchSummary(string $query, array $intent, array $products, int $total): string
    {
        if ($total === 0) {
            return "No products found for \"{$query}\". Try different keywords or a cross-reference number.";
        }

        $topNames = implode(', ', array_column(array_slice($products, 0, 3), 'name'));
        $context  = "Search: \"{$query}\". Found {$total} products. Top results: {$topNames}.";

        $systemPrompt = <<<'PROMPT'
You are a helpful industrial parts catalogue assistant.
Given a search query and result summary, write a concise 1-2 sentence response 
describing what was found. Be specific, mention product names/count. Under 60 words.
PROMPT;

        try {
            $apiKey  = config('openrouter.api_key');
            $baseUrl = rtrim(config('openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
            $model   = config('openrouter.chat_model', 'openai/gpt-4o-mini');

            if (empty($apiKey)) {
                return "Found {$total} products matching \"{$query}\".";
            }

            $response = \Illuminate\Support\Facades\Http::timeout(20)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ])
                ->post("{$baseUrl}/chat/completions", [
                    'model'       => $model,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $context],
                    ],
                    'temperature' => 0.5,
                    'max_tokens'  => 100,
                ]);

            return $response->json('choices.0.message.content', "Found {$total} products matching \"{$query}\".");

        } catch (\Throwable) {
            return "Found {$total} products matching \"{$query}\".";
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function selectColumns(): array
    {
        return [
            'id', 'sku', 'url_key', 'commodity_code', 'name', 'short_description',
            'description', 'brand', 'categories', 'category', 'product_groups',
            'store_model', 'sub_range', 'price', 'rrp_value', 'selling_surcharge',
            'qty', 'in_stock', 'weight_kg', 'is_new', 'new_from_date', 'new_to_date',
            'cross_reference', 'cross_reference_syn', 'supplier', 'supplier_v2',
            'additional_attributes', 'related_skus', 'synonym', 'popularity',
            'image', 'additional_images',
        ];
    }

    private function decodeJsonColumns(array $row): array
    {
        $jsonFields = [
            'cross_reference', 'cross_reference_syn', 'supplier', 'supplier_v2',
            'related_skus', 'additional_attributes', 'additional_images',
        ];

        foreach ($jsonFields as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $decoded = json_decode($row[$field], true);
                $row[$field] = $decoded ?? $row[$field];
            }
        }

        return $row;
    }
}
