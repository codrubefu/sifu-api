<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('services') || Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['billing_interval']);
            $table->dropColumn(['billing_interval', 'trial_days']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('services') || Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::table('services', function (Blueprint $table) {
            $table->enum('billing_interval', ['monthly', 'yearly'])->default('monthly')->after('currency');
            $table->integer('trial_days')->default(0)->after('duration_days');
            $table->index('billing_interval');
        });
    }
};
