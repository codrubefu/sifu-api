<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistent exception marker on a participation: true when the participant was
 * admitted past the access checks (check-in override or forced add with
 * `checkins.override`) and no service access was consumed for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_occurrence_user', function (Blueprint $table): void {
            $table->boolean('access_override')->default(false)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('event_occurrence_user', function (Blueprint $table): void {
            $table->dropColumn('access_override');
        });
    }
};
