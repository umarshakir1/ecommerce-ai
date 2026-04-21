<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SyncController extends Controller
{
    public function __construct(private readonly AIService $aiService) {}

    // -------------------------------------------------------------------------
    // POST /api/sync-products  (requires ApiKeyMiddleware)
    // -------------------------------------------------------------------------

    /**
     * Sync a client's product catalogue.
     *
     * Request body:
     * {
     *   "products": [
     *     {
     *       "name":        "Classic Black Hoodie",
     *       "description": "Comfortable pullover hoodie ...",
     *       "category":    "clothing",
     *       "price":       39.99,
     *       "attributes":  { "size": "L", "color": "black" },
     *       "image_url":   "https://cdn.example.com/hoodie.jpg"   // optional
     *     }
     *   ],
     *   "replace_all": false   // optional — wipe existing products before sync
     * }
     */
    public function sync(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'products'                    => ['required', 'array', 'min:1'],
            'products.*.name'             => ['required', 'string', 'max:255'],
            'products.*.description'      => ['nullable', 'string'],
            'products.*.category'         => ['required', 'string', 'max:100'],
            'products.*.price'            => ['required', 'numeric', 'min:0'],
            'products.*.attributes'                    => ['nullable', 'array'],
            'products.*.attributes.brand'              => ['nullable', 'string', 'max:100'],
            'products.*.attributes.size'               => ['nullable', 'string', 'max:255'],
            'products.*.attributes.color'              => ['nullable', 'string', 'max:50'],
            'products.*.attributes.available_sizes'    => ['nullable', 'array'],
            'products.*.attributes.available_colors'   => ['nullable', 'array'],
            'products.*.sku'                           => ['nullable', 'string', 'max:100'],
            'products.*.image_url'                     => ['nullable', 'string', 'max:500'],
            'replace_all'                 => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            Log::warning('SyncController: validation failed', ['errors' => $validator->errors()->toArray()]);
            return response()->json([
                'error'  => 'Validation failed',
                'errors' => $validator->errors(),
                'detail' => collect($validator->errors()->toArray())
                    ->map(fn($msgs, $field) => "{$field}: " . implode(', ', $msgs))
                    ->values()
                    ->take(5)
                    ->implode(' | '),
            ], 422);
        }

        /** @var User $user */
        $user       = $request->attributes->get('authenticated_user');
        $clientId   = $user->client_id;
        $products   = $request->input('products');
        $replaceAll = $request->boolean('replace_all', false);

        if ($replaceAll) {
            Product::where('client_id', $clientId)->delete();
            Log::info("SyncController: replaced all products for client [{$clientId}]");
        }

        $synced  = 0;
        $failed  = 0;
        $results = [];
        $total   = count($products);

        foreach ($products as $index => $productData) {
            try {
                $attributes  = $productData['attributes'] ?? [];
                // Fallback: use product name when description is missing
                $productData['description'] = $productData['description'] ?? $productData['name'];

                $product = Product::updateOrCreate(
                    [
                        'client_id' => $clientId,
                        'name'      => $productData['name'],
                    ],
                    [
                        'sku'              => $productData['sku'] ?? null,
                        'brand'            => $attributes['brand'] ?? null,
                        'description'      => $productData['description'],
                        'category'         => $productData['category'],
                        'price'            => (float) $productData['price'],
                        'color'            => $attributes['color'] ?? null,
                        'available_colors' => $attributes['available_colors'] ?? null,
                        'size'             => $attributes['size'] ?? null,
                        'available_sizes'  => $attributes['available_sizes'] ?? null,
                        'image'            => $productData['image_url'] ?? null,
                        'in_stock'         => true,
                        'embedding'        => null,
                    ]
                );

                $embeddingText = $product->getEmbeddingText();
                $embedding     = $this->aiService->generateEmbedding($embeddingText);

                if ($embedding !== null) {
                    $product->update(['embedding' => json_encode($embedding)]);
                    $embeddingStatus = 'generated';
                } else {
                    $embeddingStatus = 'failed';
                    Log::warning("SyncController: embedding failed for product [{$product->id}] {$product->name}");
                }

                $results[] = [
                    'id'        => $product->id,
                    'name'      => $product->name,
                    'status'    => 'synced',
                    'embedding' => $embeddingStatus,
                ];
                $synced++;

            } catch (\Throwable $e) {
                Log::error('SyncController::sync product error', [
                    'client_id' => $clientId,
                    'product'   => $productData['name'] ?? 'unknown',
                    'error'     => $e->getMessage(),
                ]);

                $results[] = [
                    'name'   => $productData['name'] ?? 'unknown',
                    'status' => 'failed',
                    'error'  => $e->getMessage(),
                ];
                $failed++;
            }

            // Respect embedding API rate limits between requests (not after the last one)
            if ($index < $total - 1) {
                usleep(50_000); // 50 ms
            }
        }

        Log::info("SyncController: sync complete for client [{$clientId}]", [
            'total'  => $total,
            'synced' => $synced,
            'failed' => $failed,
        ]);

        return response()->json([
            'message'  => "Sync complete. {$synced} product(s) synced, {$failed} failed.",
            'summary'  => [
                'total'  => $total,
                'synced' => $synced,
                'failed' => $failed,
            ],
            'products' => $results,
        ], $failed === 0 ? 200 : 207);
    }
}
