<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time reset: visits recorded before the client-IP fix were
     * over-counted (rotating IPv6 / proxy hops) and can't be merged.
     */
    public function up(): void
    {
        DB::table('visits')->delete();
    }

    public function down(): void
    {
        //
    }
};
