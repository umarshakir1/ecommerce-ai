<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->index();
            $table->string('attribute_type');  // brand | category | size | color | tag
            $table->string('attribute_value'); // e.g. "Nike", "shoes", "L", "black"
            $table->float('boost_weight')->default(0.5); // 0.0 – 1.0
            $table->timestamps();

            $table->unique(['client_id', 'attribute_type', 'attribute_value'], 'cp_client_type_value_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_priorities');
    }
};
