<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        // Multi-tenancy
        'client_id',
        // Identification
        'sku', 'url_key', 'commodity_code',
        // Core text
        'name', 'description', 'short_description', 'notes', 'synonym', 'conind',
        // Categorisation
        'brand', 'category', 'categories', 'product_groups', 'store_model', 'sub_range',
        // Variants (chat-compat)
        'color', 'available_colors', 'size', 'available_sizes', 'image',
        // Pricing
        'price', 'rrp_value', 'selling_surcharge',
        // Inventory
        'qty', 'allow_backorders', 'website_id', 'in_stock',
        // Physical
        'weight_kg', 'package_width', 'package_depth', 'package_length',
        // Flags
        'is_deleted', 'is_updated', 'is_new', 'is_images_updated',
        // Dates
        'new_from_date', 'new_to_date',
        // JSON multi-value
        'tags', 'cross_reference', 'cross_reference_syn',
        'supplier', 'supplier_v2', 'additional_attributes',
        'related_skus', 'crosssell_skus', 'upsell_skus', 'additional_images',
        // AI / RAG
        'embedding', 'popularity',
    ];

    protected $casts = [
        // JSON arrays
        'tags'                => 'array',
        'available_sizes'     => 'array',
        'available_colors'    => 'array',
        'cross_reference'     => 'array',
        'cross_reference_syn' => 'array',
        'supplier'            => 'array',
        'supplier_v2'         => 'array',
        'related_skus'        => 'array',
        'crosssell_skus'      => 'array',
        'upsell_skus'         => 'array',
        'additional_images'   => 'array',
        // JSON object
        'additional_attributes' => 'array',
        // Numerics
        'price'            => 'float',
        'rrp_value'        => 'float',
        'selling_surcharge' => 'float',
        'weight_kg'        => 'float',
        'package_width'    => 'float',
        'package_depth'    => 'float',
        'package_length'   => 'float',
        'qty'              => 'integer',
        'allow_backorders' => 'integer',
        'website_id'       => 'integer',
        'popularity'       => 'integer',
        // Booleans
        'in_stock'          => 'boolean',
        'is_deleted'        => 'boolean',
        'is_updated'        => 'boolean',
        'is_new'            => 'boolean',
        'is_images_updated' => 'boolean',
        // Dates
        'new_from_date' => 'date',
        'new_to_date'   => 'date',
        // embedding stored as raw JSON string; decoded manually in VectorSearchService
    ];

    /**
     * Return the embedding as a PHP float array.
     * Returns null if no embedding has been generated yet.
     */
    public function getEmbeddingVector(): ?array
    {
        if (empty($this->embedding)) {
            return null;
        }

        return json_decode($this->embedding, true);
    }

    /**
     * Build the text corpus used for embedding generation.
     * Combines name, description, category, color, size, and tags.
     */
    public function getEmbeddingText(): string
    {
        $parts = [
            $this->name,
            $this->short_description,
            $this->description,
            $this->brand,
            $this->categories ?? $this->category,
            $this->product_groups,
            $this->store_model,
            $this->sub_range,
            $this->color,
            $this->size,
        ];

        if (!empty($this->sku)) {
            $parts[] = 'SKU: ' . $this->sku;
        }

        if (!empty($this->commodity_code)) {
            $parts[] = 'Commodity: ' . $this->commodity_code;
        }

        if (!empty($this->synonym)) {
            $parts[] = 'Also known as: ' . $this->synonym;
        }

        if (!empty($this->notes)) {
            $parts[] = $this->notes;
        }

        if (!empty($this->cross_reference)) {
            $refs = is_array($this->cross_reference)
                ? implode(', ', $this->cross_reference)
                : $this->cross_reference;
            $parts[] = 'Cross references: ' . $refs;
        }

        if (!empty($this->supplier)) {
            $sups = is_array($this->supplier)
                ? implode(', ', $this->supplier)
                : $this->supplier;
            $parts[] = 'Suppliers: ' . $sups;
        }

        if (!empty($this->available_sizes)) {
            $parts[] = 'Available sizes: ' . implode(', ', $this->available_sizes);
        }

        if (!empty($this->available_colors)) {
            $parts[] = 'Available colors: ' . implode(', ', $this->available_colors);
        }

        if (!empty($this->tags)) {
            $parts[] = implode(' ', $this->tags);
        }

        return implode('. ', array_filter($parts));
    }
}
