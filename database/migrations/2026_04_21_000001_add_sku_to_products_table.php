<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No-op: sku is now defined in the base create_products_table migration.
    }

    public function down(): void
    {
        // No-op.
    }
};
