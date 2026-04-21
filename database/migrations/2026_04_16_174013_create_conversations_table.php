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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();   // browser session identifier
            $table->enum('role', ['user', 'assistant']);
            $table->text('message');
            $table->json('products')->nullable();    // recommended product IDs/snippets
            $table->json('extracted_intent')->nullable(); // parsed query attributes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
