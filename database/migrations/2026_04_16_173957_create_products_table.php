<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('category');
            $table->string('color')->nullable();
            $table->string('size')->nullable();         // S, M, L, XL, XXL or shoe sizes
            $table->decimal('price', 10, 2);
            $table->string('image')->nullable();        // image path/URL
            $table->json('tags')->nullable();           // searchable keyword tags
            $table->longText('embedding')->nullable();  // JSON-encoded float vector
            $table->unsignedInteger('popularity')->default(0); // view/purchase count
            $table->boolean('in_stock')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
