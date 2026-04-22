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
        // Core identification
        'sku', 'url_key',
        // Core text
        'name', 'description', 'short_description',
        // Core pricing
        'price', 'rrp_value',
        // Core inventory
        'qty',
        // Core physical
        'weight_kg',
        // Core media
        'base_image', 'thumbnail_image',
        // Core status / dates
        'is_deleted', 'is_new', 'new_from_date', 'new_to_date',
        // JSON multi-value (first-class search targets)
        'cross_reference', 'suppliers', 'categories',
        // Platform-specific attributes catchall
        'attributes',
        // AI / RAG
        'embedding', 'popularity',
    ];

    protected $casts = [
        // JSON arrays
        'cross_reference' => 'array',
        'suppliers'       => 'array',
        'categories'      => 'array',
        // JSON object (platform-specific attributes)
        'attributes'      => 'array',
        // Numerics
        'price'      => 'float',
        'rrp_value'  => 'float',
        'weight_kg'  => 'float',
        'qty'        => 'integer',
        'popularity' => 'integer',
        // Booleans
        'is_deleted' => 'boolean',
        'is_new'     => 'boolean',
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
        $attrs = (array) ($this->getAttribute('attributes') ?? []);

        $parts = [
            $this->name,
            $this->short_description,
            $this->description,
        ];

        if (!empty($this->sku)) {
            $parts[] = 'SKU: ' . $this->sku;
        }

        // Categories JSON array
        if (!empty($this->categories)) {
            $cats = is_array($this->categories)
                ? implode(', ', $this->categories)
                : $this->categories;
            $parts[] = $cats;
        }

        // Suppliers JSON array
        if (!empty($this->suppliers)) {
            $sups = is_array($this->suppliers)
                ? implode(', ', $this->suppliers)
                : $this->suppliers;
            $parts[] = 'Suppliers: ' . $sups;
        }

        // Cross-reference JSON array
        if (!empty($this->cross_reference)) {
            $refs = is_array($this->cross_reference)
                ? implode(', ', $this->cross_reference)
                : $this->cross_reference;
            $parts[] = 'Cross references: ' . $refs;
        }

        // From attributes JSON: fields valuable for semantic search
        foreach (['brand', 'commodity_code', 'synonym', 'notes', 'product_groups', 'store_model', 'sub_range', 'color', 'size'] as $key) {
            if (!empty($attrs[$key]) && is_string($attrs[$key])) {
                $parts[] = $attrs[$key];
            }
        }

        return implode('. ', array_filter($parts));
    }
}
