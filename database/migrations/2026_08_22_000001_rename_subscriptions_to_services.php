<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions') && ! Schema::hasTable('services')) {
            Schema::rename('subscriptions', 'services');
        }

        if (Schema::hasTable('subscription_user') && ! Schema::hasTable('service_user')) {
            Schema::rename('subscription_user', 'service_user');
        }

        $this->renameColumnIfExists('service_user', 'subscription_id', 'service_id');
        $this->renameColumnIfExists('sms_messages', 'subscription_id', 'service_id');
        $this->renameColumnIfExists('sms_messages', 'subscription_user_id', 'service_user_id');
        $this->renameColumnIfExists('events', 'requires_active_subscription', 'requires_active_service');
        $this->renameColumnIfExists('events', 'required_subscription_id', 'required_service_id');

        if (Schema::hasTable('payments')) {
            DB::table('payments')
                ->where('model_type', 'subscription_user')
                ->update(['model_type' => 'service_user']);
        }

        if (Schema::hasTable('rights')) {
            DB::table('rights')->where('name', 'like', 'subscriptions.%')->get(['id', 'name', 'label', 'description'])
                ->each(function (object $right): void {
                    DB::table('rights')->where('id', $right->id)->update([
                        'name' => str_replace('subscriptions.', 'services.', $right->name),
                        'label' => str_replace(['Subscriptions', 'subscriptions'], ['Services', 'services'], (string) $right->label),
                        'description' => str_replace(['Subscriptions', 'subscriptions'], ['Services', 'services'], (string) $right->description),
                    ]);
                });
        }

        if (Schema::hasTable('audit_logs')) {
            DB::table('audit_logs')->where('event_type', 'like', 'subscription.%')->get(['id', 'event_type'])
                ->each(fn (object $log): int => DB::table('audit_logs')->where('id', $log->id)->update([
                    'event_type' => str_replace('subscription.', 'service.', $log->event_type),
                ]));
        }

        if (Schema::hasTable('sms_messages')) {
            DB::table('sms_messages')->where('type', 'subscription_expiring')->update(['type' => 'service_expiring']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sms_messages')) {
            DB::table('sms_messages')->where('type', 'service_expiring')->update(['type' => 'subscription_expiring']);
        }

        if (Schema::hasTable('audit_logs')) {
            DB::table('audit_logs')->where('event_type', 'like', 'service.%')->get(['id', 'event_type'])
                ->each(fn (object $log): int => DB::table('audit_logs')->where('id', $log->id)->update([
                    'event_type' => str_replace('service.', 'subscription.', $log->event_type),
                ]));
        }

        if (Schema::hasTable('rights')) {
            DB::table('rights')->where('name', 'like', 'services.%')->get(['id', 'name', 'label', 'description'])
                ->each(function (object $right): void {
                    DB::table('rights')->where('id', $right->id)->update([
                        'name' => str_replace('services.', 'subscriptions.', $right->name),
                        'label' => str_replace(['Services', 'services'], ['Subscriptions', 'subscriptions'], (string) $right->label),
                        'description' => str_replace(['Services', 'services'], ['Subscriptions', 'subscriptions'], (string) $right->description),
                    ]);
                });
        }

        if (Schema::hasTable('payments')) {
            DB::table('payments')
                ->where('model_type', 'service_user')
                ->update(['model_type' => 'subscription_user']);
        }

        $this->renameColumnIfExists('events', 'required_service_id', 'required_subscription_id');
        $this->renameColumnIfExists('events', 'requires_active_service', 'requires_active_subscription');
        $this->renameColumnIfExists('sms_messages', 'service_user_id', 'subscription_user_id');
        $this->renameColumnIfExists('sms_messages', 'service_id', 'subscription_id');
        $this->renameColumnIfExists('service_user', 'service_id', 'subscription_id');

        if (Schema::hasTable('service_user') && ! Schema::hasTable('subscription_user')) {
            Schema::rename('service_user', 'subscription_user');
        }

        if (Schema::hasTable('services') && ! Schema::hasTable('subscriptions')) {
            Schema::rename('services', 'subscriptions');
        }
    }

    private function renameColumnIfExists(string $table, string $from, string $to): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $from) || Schema::hasColumn($table, $to)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($from, $to): void {
            $table->renameColumn($from, $to);
        });
    }
};
