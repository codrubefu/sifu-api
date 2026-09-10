<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('requires_payment')->default(false)->after('required_service_id');
            $table->decimal('payment_amount', 10, 2)->nullable()->after('requires_payment');
            $table->string('payment_type')->nullable()->after('payment_amount');

            $table->index('requires_payment');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['requires_payment']);
            $table->dropColumn(['requires_payment', 'payment_amount', 'payment_type']);
        });
    }
};