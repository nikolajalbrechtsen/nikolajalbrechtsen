<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the visitor's IP and where they come from, so the admin can see it.
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('ip', 45)->nullable()->after('ip_hash');
            $table->char('country', 2)->nullable()->after('ip');
            $table->string('region', 100)->nullable()->after('country');
            $table->string('city', 100)->nullable()->after('region');
            $table->string('referrer', 255)->nullable()->after('city');
            $table->string('last_path', 255)->nullable()->after('referrer');
            $table->string('user_agent', 255)->nullable()->after('last_path');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['ip', 'country', 'region', 'city', 'referrer', 'last_path', 'user_agent']);
        });
    }
};
