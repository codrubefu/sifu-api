<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('services') || ! Schema::hasTable('service_user') || Schema::hasTable('subscriptions') || Schema::hasTable('subscription_user')) {
            return;
        }

        Schema::table('services', function (Blueprint $table): void {
            $table->string('type')->default('membership')->after('description');
            $table->string('expiration_rule')->default('duration')->after('duration_days');
            $table->dateTime('fixed_expires_at')->nullable()->after('expiration_rule');
            $table->unsignedInteger('grace_period_days')->default(0)->after('fixed_expires_at');
            $table->unsignedInteger('max_accesses')->nullable()->after('grace_period_days');
        });

        Schema::table('service_user', function (Blueprint $table): void {
            $table->dateTime('start_date')->nullable()->change();
            $table->dateTime('expires_at')->nullable()->change();
            $table->string('status')->default('pending')->after('user_id');
            $table->unsignedInteger('accesses_used')->default(0)->after('expires_at');
            $table->dateTime('activated_at')->nullable()->after('accesses_used');
            $table->dateTime('suspended_at')->nullable()->after('activated_at');
            $table->dateTime('resume_at')->nullable()->after('suspended_at');
            $table->text('status_reason')->nullable()->after('resume_at');
            $table->foreignId('activation_payment_id')->nullable()->after('status_reason')
                ->constrained('payments')->nullOnDelete();
            $table->index(['status', 'resume_at']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('services') || ! Schema::hasTable('service_user') || Schema::hasTable('subscriptions') || Schema::hasTable('subscription_user')) {
            return;
        }

        Schema::table('service_user', function (Blueprint $table): void {
            $table->dropForeign(['activation_payment_id']);
            $table->dropIndex(['status', 'resume_at']);
            $table->dropColumn(['status', 'accesses_used', 'activated_at', 'suspended_at', 'resume_at', 'status_reason', 'activation_payment_id']);
            $table->date('start_date')->nullable()->change();
            $table->date('expires_at')->nullable()->change();
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['type', 'expiration_rule', 'fixed_expires_at', 'grace_period_days', 'max_accesses']);
        });
    }
};
