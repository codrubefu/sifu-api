<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On-demand invoice for a paid event participation. The number comes from the
 * organization invoice sequence (shared with service invoices), so it is not
 * globally unique across organizations; it is indexed like service_user.invoice_number.
 * `invoiced_at` freezes the issue date (the pivot updated_at moves with status changes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_occurrence_user', function (Blueprint $table): void {
            $table->string('invoice_number')->nullable()->after('access_override');
            $table->timestamp('invoiced_at')->nullable()->after('invoice_number');
            $table->index('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('event_occurrence_user', function (Blueprint $table): void {
            $table->dropIndex(['invoice_number']);
            $table->dropColumn(['invoice_number', 'invoiced_at']);
        });
    }
};
