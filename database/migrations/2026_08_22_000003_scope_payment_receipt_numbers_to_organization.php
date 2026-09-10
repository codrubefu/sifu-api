<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('payments'));

        if ($indexes->contains(fn (array $index): bool => $index['name'] === 'payments_receipt_number_unique')) {
            Schema::table('payments', fn (Blueprint $table): Blueprint => $table->dropUnique('payments_receipt_number_unique'));
        }

        if (! $indexes->contains(fn (array $index): bool => $index['name'] === 'payments_organization_receipt_number_unique')) {
            Schema::table('payments', fn (Blueprint $table): Blueprint => $table->unique(['organization_id', 'receipt_number'], 'payments_organization_receipt_number_unique'));
        }
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('payments'));

        if ($indexes->contains(fn (array $index): bool => $index['name'] === 'payments_organization_receipt_number_unique')) {
            Schema::table('payments', fn (Blueprint $table): Blueprint => $table->dropUnique('payments_organization_receipt_number_unique'));
        }

        if (! $indexes->contains(fn (array $index): bool => $index['name'] === 'payments_receipt_number_unique')) {
            Schema::table('payments', fn (Blueprint $table): Blueprint => $table->unique('receipt_number'));
        }
    }
};
