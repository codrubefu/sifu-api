<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_user', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_user', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('user_id');
                $table->index('invoice_number');
            }

            if (! Schema::hasColumn('service_user', 'bill_number')) {
                $table->string('bill_number')->nullable()->after('invoice_number');
                $table->index('bill_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_user', function (Blueprint $table): void {
            if (Schema::hasColumn('service_user', 'invoice_number')) {
                $table->dropIndex(['invoice_number']);
            }
            if (Schema::hasColumn('service_user', 'bill_number')) {
                $table->dropIndex(['bill_number']);
            }

            $columns = array_values(array_filter(
                ['invoice_number', 'bill_number'],
                fn (string $column): bool => Schema::hasColumn('service_user', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
