<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time cleanup: visits recorded before IPs were stored have no
     * IP, location or device and can never get one.
     */
    public function up(): void
    {
        DB::table('visits')->whereNull('ip')->delete();
    }

    public function down(): void
    {
        //
    }
};
