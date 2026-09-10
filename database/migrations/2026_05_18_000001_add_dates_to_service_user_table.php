<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_user') || Schema::hasTable('subscription_user')) {
            return;
        }

        Schema::table('service_user', function (Blueprint $table) {
            $table->index(['service_id', 'user_id']);
            $table->dropUnique(['service_id', 'user_id']);
            $table->date('start_date')->nullable()->after('user_id');
            $table->date('expires_at')->nullable()->after('start_date');
            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_user') || Schema::hasTable('subscription_user')) {
            return;
        }

        Schema::table('service_user', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'expires_at']);
            $table->dropIndex(['service_id', 'user_id']);
            $table->dropColumn(['start_date', 'expires_at']);
            $table->unique(['service_id', 'user_id']);
        });
    }
};
