<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('client_id')->unique()->after('id');
            $table->string('api_key', 64)->unique()->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['client_id']);
            $table->dropUnique(['api_key']);
            $table->dropColumn(['client_id', 'api_key']);
        });
    }
};
