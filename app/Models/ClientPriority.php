<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPriority extends Model
{
    protected $fillable = [
        'client_id',
        'attribute_type',
        'attribute_value',
        'boost_weight',
    ];

    protected $casts = [
        'boost_weight' => 'float',
    ];

    /**
     * Valid attribute types that can be prioritised.
     */
    public const VALID_TYPES = ['brand', 'category', 'size', 'color', 'tag'];
}
