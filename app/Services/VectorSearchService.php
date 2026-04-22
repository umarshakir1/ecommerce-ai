<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * VectorSearchService
 *
 * Implements a hybrid search pipeline:
 *  1. SQL pre-filter  – narrow the candidate pool by hard attributes
 *  2. Cosine similarity – rank candidates by semantic embedding proximity
 *  3. Score boosting   – popularity, price-relevance, and client priority adjustments
 */
class VectorSearchService
{
    /**
     * Number of top products to return.
     */
    private int $topK;

    public function __construct(
        private readonly PriorityService $priorityService,
        int $topK = 5
    ) {
        $this->topK = $topK;
    }

    // -------------------------------------------------------------------------
    // MAIN ENTRY POINT
    // -------------------------------------------------------------------------

    /**
     * Run the full hybrid search and return the top-K products.
     *
     * @param  float[]  $queryEmbedding  Embedding vector for the user query
     * @param  array    $intent          Structured intent extracted from the query
     * @param  string   $clientId        Tenant client_id — ALWAYS applied as a hard filter
     * @return array                     Top products enriched with similarity_score
     */
    public function search(array $queryEmbedding, array $intent, string $clientId): array
    {
        // Step 0 – SKU match: exact first, then partial LIKE fallback
        if (!empty($intent['sku'])) {
            $skuUpper = strtoupper($intent['sku']);

            // Exact match
            $product = Product::where('client_id', $clientId)
                ->whereRaw('UPPER(sku) = ?', [$skuUpper])
                ->first();

            // Partial match fallback (handles trimmed/prefixed input e.g. "030" matching "DEMO-030")
            if (! $product) {
                $product = Product::where('client_id', $clientId)
                    ->whereRaw('UPPER(sku) LIKE ?', ['%' . $skuUpper . '%'])
                    ->first();
            }

            if ($product) {
                $arr = $product->toArray();
                $arr['similarity_score'] = 1.0;
                $arr['final_score']      = 1.0;
                return [$arr];
            }
        }

        // Step 1 – Apply SQL filters to get a manageable candidate set
        $candidates = $this->applySqlFilters($intent, $clientId);

        if ($candidates->isEmpty()) {
            // Broaden the search: drop strict filters, use all embedded products for this client
            $candidates = Product::whereNotNull('embedding')
                ->where('in_stock', true)
                ->where('client_id', $clientId)
                ->get();
        }

        if ($candidates->isEmpty()) {
            return [];
        }

        // Step 2 – Compute cosine similarity for each candidate
        $scored = $this->computeSimilarityScores($candidates, $queryEmbedding);

        // Step 3 – Apply popularity + price + client-priority boost
        $boosted = $this->applyBoostScores($scored, $intent, $clientId);

        // Step 4 – If the best similarity is too low the SQL filter probably
        //          restricted the wrong category. Retry over the full catalogue
        //          (no SQL filters) so cosine similarity can find the real match.
        $bestSimilarity = $boosted->max('similarity_score') ?? 0.0;
        if ($bestSimilarity < 0.25) {
            $allProducts = Product::whereNotNull('embedding')
                ->where('in_stock', true)
                ->where('client_id', $clientId)
                ->get();

            if ($allProducts->count() > $candidates->count()) {
                $scored  = $this->computeSimilarityScores($allProducts, $queryEmbedding);
                $boosted = $this->applyBoostScores($scored, $intent, $clientId);
            }
        }

        // Step 5 – Sort descending by final_score and return top-K
        return $boosted
            ->sortByDesc('final_score')
            ->take($this->topK)
            ->values()
            ->toArray();
    }

    // -------------------------------------------------------------------------
    // SQL PRE-FILTERING
    // -------------------------------------------------------------------------

    /**
     * Build a filtered Eloquent query from the extracted intent attributes.
     * All filters are optional — missing attributes are simply skipped.
     * client_id is ALWAYS applied — no cross-tenant leakage possible.
     */
    private function applySqlFilters(array $intent, string $clientId): Collection
    {
        $query = Product::where('client_id', $clientId)
            ->whereNotNull('embedding')
            ->where('is_deleted', false);

        // SKU filter — exact match (hard override lives in search() above)
        if (!empty($intent['sku'])) {
            $query->whereRaw('UPPER(sku) = ?', [strtoupper($intent['sku'])]);
        }

        // Color — now stored in attributes JSON
        if (!empty($intent['color'])) {
            $like = '%' . strtolower($intent['color']) . '%';
            $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '\$.color'))) LIKE ?", [$like]);
        }

        // Size — now stored in attributes JSON
        if (!empty($intent['size'])) {
            $val = strtolower($intent['size']);
            $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '\$.size'))) = ?", [$val]);
        }

        // Brand — check attributes JSON + suppliers JSON array
        if (!empty($intent['brand'])) {
            $like = '%' . strtolower($intent['brand']) . '%';
            $query->where(function ($q) use ($like) {
                $q->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(`attributes`, '\$.brand'))) LIKE ?", [$like])
                  ->orWhereRaw("JSON_SEARCH(`suppliers`, 'one', ?) IS NOT NULL", [$like]);
            });
        }

        // Category — check categories JSON array + attributes JSON
        if (!empty($intent['category'])) {
            $like = '%' . strtolower($intent['category']) . '%';
            $query->where(function ($q) use ($like) {
                $q->orWhereRaw("JSON_SEARCH(`categories`, 'one', ?) IS NOT NULL", [$like])
                  ->orWhereRaw("JSON_SEARCH(`attributes`, 'all', ?) IS NOT NULL", [$like]);
            });
        }

        // Price range filter (price is still a core column)
        if (!empty($intent['price_range'])) {
            $min = $intent['price_range']['min'] ?? null;
            $max = $intent['price_range']['max'] ?? null;
            if ($min !== null) {
                $query->where('price', '>=', (float) $min);
            }
            if ($max !== null) {
                $query->where('price', '<=', (float) $max);
            }
        }

        return $query->get();
    }

    // -------------------------------------------------------------------------
    // COSINE SIMILARITY
    // -------------------------------------------------------------------------

    /**
     * Compute cosine similarity between the query embedding and each product.
     * Attaches a 'similarity_score' to every product in the collection.
     *
     * similarity(A, B) = dot(A, B) / (|A| * |B|)
     */
    private function computeSimilarityScores(
        Collection $candidates,
        array $queryEmbedding
    ): Collection {
        // Pre-compute query vector magnitude once
        $queryMagnitude = $this->magnitude($queryEmbedding);

        if ($queryMagnitude == 0) {
            // Cannot compute similarity with a zero vector; score everything 0
            return $candidates->map(function (Product $product) {
                $arr = $product->toArray();
                $arr['similarity_score'] = 0.0;
                return $arr;
            });
        }

        return $candidates->map(function (Product $product) use ($queryEmbedding, $queryMagnitude) {
            $productVector = $product->getEmbeddingVector();

            $score = 0.0;
            if ($productVector !== null && count($productVector) === count($queryEmbedding)) {
                $dotProduct       = $this->dotProduct($queryEmbedding, $productVector);
                $productMagnitude = $this->magnitude($productVector);

                if ($productMagnitude > 0) {
                    $score = $dotProduct / ($queryMagnitude * $productMagnitude);
                    // Clamp to [-1, 1] to handle floating-point precision drift
                    $score = max(-1.0, min(1.0, $score));
                }
            }

            $arr = $product->toArray();
            $arr['similarity_score'] = $score;
            return $arr;
        });
    }

    // -------------------------------------------------------------------------
    // SCORE BOOSTING
    // -------------------------------------------------------------------------

    /**
     * Compute a final composite score by blending:
     *  - 60% cosine similarity
     *  - 15% popularity (normalised)
     *  -  5% price relevance
     *  - 20% client-configured attribute priority
     *
     * Attaches 'final_score' and 'score_breakdown' to each product array.
     */
    private function applyBoostScores(Collection $scored, array $intent, string $clientId): Collection
    {
        // Normalise popularity across the candidate set
        $maxPopularity = $scored->max('popularity') ?: 1;

        // Load priority rules once for the whole candidate set
        $priorityRules = $this->priorityService->rulesForClient($clientId);

        return $scored->map(function (array $product) use ($maxPopularity, $intent, $priorityRules) {
            $similarityScore  = (float) ($product['similarity_score'] ?? 0.0);
            $popularityScore  = ($product['popularity'] ?? 0) / $maxPopularity;
            $priceScore       = $this->computePriceScore($product['price'] ?? 0, $intent);
            $priorityScore    = $this->priorityService->scoreProduct($product, $priorityRules);

            $finalScore = ($similarityScore * 0.60)
                        + ($popularityScore  * 0.15)
                        + ($priceScore       * 0.05)
                        + ($priorityScore    * 0.20);

            $product['final_score'] = round($finalScore, 6);
            $product['score_breakdown'] = [
                'similarity' => round($similarityScore, 4),
                'popularity' => round($popularityScore, 4),
                'price'      => round($priceScore, 4),
                'priority'   => round($priorityScore, 4),
            ];

            return $product;
        });
    }

    /**
     * Compute a 0-1 price relevance score.
     *
     * - If user specifies a price range, products inside it score 1.0, outside 0.0
     * - If no price preference, we give a soft preference to mid-price items
     */
    private function computePriceScore(float $price, array $intent): float
    {
        $min = $intent['price_range']['min'] ?? null;
        $max = $intent['price_range']['max'] ?? null;

        if ($min !== null || $max !== null) {
            $inRange = ($min === null || $price >= $min) && ($max === null || $price <= $max);
            return $inRange ? 1.0 : 0.0;
        }

        // Soft scoring: assume a sweet spot of $20–$200 for general clothing
        if ($price <= 0) {
            return 0.0;
        }

        if ($price <= 20) {
            return 0.5;
        }

        if ($price <= 200) {
            return 1.0 - (($price - 20) / 180) * 0.5; // 1.0 at $20 → 0.5 at $200
        }

        return 0.2; // expensive items get a small score
    }

    // -------------------------------------------------------------------------
    // MATH HELPERS
    // -------------------------------------------------------------------------

    /**
     * Dot product of two equal-length float arrays.
     *
     * @param  float[]  $a
     * @param  float[]  $b
     * @return float
     */
    private function dotProduct(array $a, array $b): float
    {
        $sum = 0.0;
        $len = count($a);

        for ($i = 0; $i < $len; $i++) {
            $sum += $a[$i] * $b[$i];
        }

        return $sum;
    }

    /**
     * Euclidean magnitude (L2 norm) of a vector.
     *
     * @param  float[]  $v
     * @return float
     */
    private function magnitude(array $v): float
    {
        $sum = 0.0;
        foreach ($v as $val) {
            $sum += $val * $val;
        }
        return sqrt($sum);
    }
}
