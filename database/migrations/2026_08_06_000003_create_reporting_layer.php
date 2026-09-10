<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('bank_reference')->nullable()->after('provider_transaction_id');
            $table->timestamp('reconciled_at')->nullable()->after('bank_reference');
            $table->index(['organization_id', 'paid_at']);
        });

        Schema::create('segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->json('criteria');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('report_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('format', 4);
            $table->json('filters');
            $table->string('status', 20)->default('pending');
            $table->string('path')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('segments');
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'paid_at']);
            $table->dropColumn(['bank_reference', 'reconciled_at']);
        });
    }
};
