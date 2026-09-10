<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rights')->update(['organization_id' => null]);
    }

    public function down(): void
    {
        //
    }
};
