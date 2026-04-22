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

            // ── Identification ────────────────────────────────────────────────
            $table->string('sku')->nullable()->index();
            $table->string('url_key')->nullable()->index();
            $table->string('commodity_code')->nullable()->index();

            // ── Core text (used by chat RAG + fulltext search) ────────────────
            $table->text('name')->nullable();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->text('notes')->nullable();
            $table->text('synonym')->nullable();
            $table->string('conind')->nullable();

            // ── Categorisation ────────────────────────────────────────────────
            $table->string('brand')->nullable();
            $table->string('category')->nullable();      // chat-compat alias
            $table->string('categories')->nullable();    // CSV: pipe/comma list
            $table->string('product_groups')->nullable();
            $table->string('store_model')->nullable();
            $table->string('sub_range')->nullable();

            // ── Variants (chat-compat) ─────────────────────────────────────────
            $table->string('color')->nullable();
            $table->json('available_colors')->nullable();
            $table->string('size')->nullable();
            $table->json('available_sizes')->nullable();
            $table->string('image')->nullable();

            // ── Pricing ───────────────────────────────────────────────────────
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('rrp_value', 10, 2)->nullable();
            $table->decimal('selling_surcharge', 10, 2)->nullable();

            // ── Inventory ─────────────────────────────────────────────────────
            $table->integer('qty')->nullable();
            $table->integer('allow_backorders')->nullable();
            $table->integer('website_id')->nullable();
            $table->boolean('in_stock')->default(true);

            // ── Physical dimensions ───────────────────────────────────────────
            $table->float('weight_kg')->nullable();
            $table->float('package_width')->nullable();
            $table->float('package_depth')->nullable();
            $table->float('package_length')->nullable();

            // ── Status flags ──────────────────────────────────────────────────
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_updated')->default(false);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_images_updated')->default(false);

            // ── Dates ─────────────────────────────────────────────────────────
            $table->date('new_from_date')->nullable();
            $table->date('new_to_date')->nullable();

            // ── JSON multi-value fields ───────────────────────────────────────
            $table->json('tags')->nullable();
            $table->json('cross_reference')->nullable();
            $table->json('cross_reference_syn')->nullable();
            $table->json('supplier')->nullable();
            $table->json('supplier_v2')->nullable();
            $table->json('additional_attributes')->nullable();
            $table->json('related_skus')->nullable();
            $table->json('crosssell_skus')->nullable();
            $table->json('upsell_skus')->nullable();
            $table->json('additional_images')->nullable();

            // ── AI / RAG ──────────────────────────────────────────────────────
            $table->longText('embedding')->nullable();
            $table->unsignedInteger('popularity')->default(0);

            $table->timestamps();
        });

        // Fulltext index — must use DB::statement for TEXT column support
        DB::statement('ALTER TABLE products ADD FULLTEXT INDEX products_fulltext_idx
            (name, description, short_description, sku, synonym, commodity_code,
             url_key, conind, categories, product_groups, store_model, sub_range)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
