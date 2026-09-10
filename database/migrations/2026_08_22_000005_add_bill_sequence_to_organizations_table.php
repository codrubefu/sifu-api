<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            if (! Schema::hasColumn('organizations', 'bill_code')) {
                $table->string('bill_code')->default('BILL')->after('invoice_number');
            }
            if (! Schema::hasColumn('organizations', 'bill_number')) {
                $table->unsignedBigInteger('bill_number')->default(0)->after('bill_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['bill_code', 'bill_number'],
                fn (string $column): bool => Schema::hasColumn('organizations', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
