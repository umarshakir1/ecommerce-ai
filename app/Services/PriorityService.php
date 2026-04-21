<?php

namespace App\Services;

use App\Models\ClientPriority;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * PriorityService
 *
 * Loads a client's attribute priority rules and computes a 0–1 priority
 * boost score for any product array.
 *
 * Priority rules are cached per client for 5 minutes to avoid repeated DB
 * lookups on every search request.
 *
 * Scoring formula (applied on top of the base vector score):
 *   priority_score = min(1.0, sum of boost_weight for all matching rules)
 *
 * Matched attributes:
 *   brand    → product['brand']
 *   category → product['category'] (partial match)
 *   size     → product['size'] (exact, case-insensitive)
 *   color    → product['color'] (partial, case-insensitive)
 *   tag      → product['tags'] array contains the value
 */
class PriorityService
{
    /**
     * Cache TTL in seconds.
     */
    private const CACHE_TTL = 300;

    /**
     * Load all priority rules for a client (cached).
     *
     * @return Collection<ClientPriority>
     */
    public function rulesForClient(string $clientId): Collection
    {
        return Cache::remember(
            "client_priorities:{$clientId}",
            self::CACHE_TTL,
            fn () => ClientPriority::where('client_id', $clientId)->get()
        );
    }

    /**
     * Compute a 0–1 priority boost score for a product array.
     *
     * @param  array       $product  Product data (toArray() result)
     * @param  Collection  $rules    Priority rules for this client
     * @return float                 Clamped to [0, 1]
     */
    public function scoreProduct(array $product, Collection $rules): float
    {
        if ($rules->isEmpty()) {
            return 0.0;
        }

        $totalBoost = 0.0;

        foreach ($rules as $rule) {
            if ($this->productMatchesRule($product, $rule)) {
                $totalBoost += $rule->boost_weight;
            }
        }

        return min(1.0, $totalBoost);
    }

    /**
     * Flush the priority cache for a client.
     * Call this whenever rules are created, updated, or deleted.
     */
    public function flushCache(string $clientId): void
    {
        Cache::forget("client_priorities:{$clientId}");
    }

    // -------------------------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------------------------

    private function productMatchesRule(array $product, ClientPriority $rule): bool
    {
        $type  = $rule->attribute_type;
        $value = strtolower(trim($rule->attribute_value));

        return match ($type) {
            'brand'    => $this->matchBrand($product, $value),
            'category' => $this->matchCategory($product, $value),
            'size'     => $this->matchSize($product, $value),
            'color'    => $this->matchColor($product, $value),
            'tag'      => $this->matchTag($product, $value),
            default    => false,
        };
    }

    private function matchBrand(array $product, string $value): bool
    {
        $brand = strtolower(trim($product['brand'] ?? ''));
        return $brand !== '' && str_contains($brand, $value);
    }

    private function matchCategory(array $product, string $value): bool
    {
        $category = strtolower(trim($product['category'] ?? ''));
        return $category !== '' && str_contains($category, $value);
    }

    private function matchSize(array $product, string $value): bool
    {
        $size = strtolower(trim($product['size'] ?? ''));
        return $size === $value;
    }

    private function matchColor(array $product, string $value): bool
    {
        $color = strtolower(trim($product['color'] ?? ''));
        return $color !== '' && str_contains($color, $value);
    }

    private function matchTag(array $product, string $value): bool
    {
        $tags = $product['tags'] ?? [];
        if (is_string($tags)) {
            $tags = json_decode($tags, true) ?? [];
        }
        foreach ($tags as $tag) {
            if (str_contains(strtolower((string) $tag), $value)) {
                return true;
            }
        }
        return false;
    }
}
