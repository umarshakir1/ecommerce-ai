<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: column may already exist if migration failed mid-run previously
        if (! Schema::hasColumn('products', 'client_id')) {
            Schema::table('products', function (Blueprint $table) {
                // Default '' so legacy/seed products are never matched by any real client UUID
                $table->string('client_id')->default('')->after('id');
                $table->index('client_id');
            });
        }

        // Composite index — must use DB::statement with prefix lengths to avoid
        // MySQL "key too long" errors on utf8mb4 tables.
        $exists = DB::selectOne(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'products'
               AND index_name   = 'products_client_cat_color_size_idx'"
        );

        if (($exists->cnt ?? 0) == 0) {
            DB::statement(
                'ALTER TABLE `products`
                 ADD INDEX `products_client_cat_color_size_idx`
                 (`client_id`(100), `category`(100), `color`(50), `size`(20))'
            );
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $sm      = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = array_keys($sm->listTableIndexes('products'));

            if (in_array('products_client_cat_color_size_idx', $indexes)) {
                $table->dropIndex('products_client_cat_color_size_idx');
            }

            if (in_array('products_client_id_index', $indexes)) {
                $table->dropIndex(['client_id']);
            }

            if (Schema::hasColumn('products', 'client_id')) {
                $table->dropColumn('client_id');
            }
        });
    }
};
