<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'sku',
        'brand',
        'name',
        'description',
        'category',
        'color',
        'available_colors',
        'size',
        'available_sizes',
        'price',
        'image',
        'tags',
        'embedding',
        'popularity',
        'in_stock',
    ];

    protected $casts = [
        'tags'             => 'array',
        'available_sizes'  => 'array',
        'available_colors' => 'array',
        'price'            => 'float',
        'in_stock'         => 'boolean',
        'popularity'       => 'integer',
        // embedding is stored as raw JSON string; we decode manually in VectorSearchService
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
            $this->description,
            $this->category,
            $this->brand,
            $this->color,
            $this->size,
        ];

        if (!empty($this->sku)) {
            $parts[] = 'SKU: ' . $this->sku;
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
