<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per visitor (hashed IP) per day.
     */
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->char('ip_hash', 64);
            $table->date('date');
            $table->unsignedInteger('hits')->default(1);
            $table->timestamps();

            $table->unique(['ip_hash', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
