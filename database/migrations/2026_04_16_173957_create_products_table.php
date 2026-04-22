<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // ── Multi-tenancy ─────────────────────────────────────────────────
            $table->string('client_id')->default('')->index();

            // ── Core identification (exists on every platform) ────────────────
            $table->string('sku')->nullable()->index();
            $table->string('url_key')->nullable()->index();

            // ── Core text ────────────────────────────────────────────────────
            $table->string('name')->nullable()->index();
            $table->longText('description')->nullable();
            $table->text('short_description')->nullable();

            // ── Core pricing ──────────────────────────────────────────────────
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('rrp_value', 10, 2)->nullable();

            // ── Core inventory ────────────────────────────────────────────────
            $table->integer('qty')->default(0);

            // ── Core physical ─────────────────────────────────────────────────
            $table->float('weight_kg')->nullable();

            // ── Core media ────────────────────────────────────────────────────
            $table->string('base_image')->nullable();
            $table->string('thumbnail_image')->nullable();

            // ── Core status / dates ───────────────────────────────────────────
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_new')->default(false);
            $table->date('new_from_date')->nullable();
            $table->date('new_to_date')->nullable();

            // ── JSON multi-value (first-class search targets) ─────────────────
            $table->json('cross_reference')->nullable(); // merged cross_reference + cross_reference_syn
            $table->json('suppliers')->nullable();       // merged supplier + supplier_v2
            $table->json('categories')->nullable();      // categories + product_groups + store_model + sub_range

            // ── Platform-specific / product-specific attributes ───────────────
            // Everything else lives here: brand, commodity_code, synonym, notes,
            // dimensions, flags, related SKUs, additional images, parsed
            // additional_attributes key-value pairs, etc.
            $table->json('attributes')->nullable();

            // ── AI / RAG ──────────────────────────────────────────────────────
            $table->longText('embedding')->nullable();
            $table->unsignedInteger('popularity')->default(0);

            $table->timestamps();
        });

        DB::statement('ALTER TABLE products ADD FULLTEXT INDEX products_ft_idx
            (name, description, short_description, sku, url_key)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
