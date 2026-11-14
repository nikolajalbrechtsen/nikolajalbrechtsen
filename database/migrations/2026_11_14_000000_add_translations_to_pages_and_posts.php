<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pages and posts are written in Danish; other languages are stored here,
     * e.g. {"en": {"title": "...", "blocks": [...]}}.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('blocks');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn('translations'));
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('translations'));
    }
};
