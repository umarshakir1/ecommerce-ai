<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Make api_key nullable — new users get it only after domain setup
            $table->string('api_key', 64)->nullable()->change();

            // One-time token used to set domain and generate the real api_key
            $table->string('setup_token', 64)->nullable()->unique()->after('api_key');

            // The client's registered (whitelisted) website domain
            $table->string('website_domain', 255)->nullable()->after('setup_token');

            // Admin can disable/enable a client
            $table->boolean('is_active')->default(true)->after('website_domain');

            // Tracks when the client last made a successful API call
            $table->string('connection_status', 20)->default('not_connected')->after('is_active');
            $table->timestamp('last_connected_at')->nullable()->after('connection_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['setup_token', 'website_domain', 'is_active', 'connection_status', 'last_connected_at']);
            $table->string('api_key', 64)->nullable(false)->change();
        });
    }
};
