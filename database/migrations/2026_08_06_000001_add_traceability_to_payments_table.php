<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->after('organization_id')->constrained('locations')->nullOnDelete();
            $table->string('status', 20)->default('initiated')->after('payment_type_id');
            $table->string('external_reference')->nullable()->after('status');
            $table->string('receipt_number')->nullable()->after('external_reference');
            $table->string('provider')->nullable()->after('receipt_number');
            $table->string('provider_transaction_id')->nullable()->after('provider');
            $table->json('provider_payload')->nullable()->after('provider_transaction_id');
            $table->timestamp('confirmed_at')->nullable()->after('paid_at');
            $table->timestamp('failed_at')->nullable()->after('confirmed_at');
            $table->timestamp('refunded_at')->nullable()->after('failed_at');
            $table->timestamp('cancelled_at')->nullable()->after('refunded_at');
            $table->text('failure_reason')->nullable()->after('cancelled_at');

            $table->index(['organization_id', 'status']);
            $table->unique('external_reference');
            $table->unique(['organization_id', 'receipt_number'], 'payments_organization_receipt_number_unique');
            $table->unique(['provider', 'provider_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['provider', 'provider_transaction_id']);
            $table->dropUnique('payments_organization_receipt_number_unique');
            $table->dropUnique(['external_reference']);
            $table->dropIndex(['organization_id', 'status']);
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['status', 'external_reference', 'receipt_number', 'provider', 'provider_transaction_id', 'provider_payload', 'confirmed_at', 'failed_at', 'refunded_at', 'cancelled_at', 'failure_reason']);
        });
    }
};
