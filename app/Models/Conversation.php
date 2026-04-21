<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'role',
        'message',
        'products',
        'extracted_intent',
    ];

    protected $casts = [
        'products'         => 'array',
        'extracted_intent' => 'array',
    ];
}
