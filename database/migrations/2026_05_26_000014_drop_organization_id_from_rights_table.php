<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rights', function (Blueprint $table): void {
            $table->dropIndex(['organization_id']);
            $table->dropConstrainedForeignId('organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('rights', function (Blueprint $table): void {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->nullOnDelete();
            $table->index('organization_id');
        });
    }
};
