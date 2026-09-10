<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->foreignId('segment_id')->nullable()->constrained('segments')->nullOnDelete();
        });

        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('segments')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('channel', 10);
            $table->string('subject')->nullable();
            $table->text('content');
            $table->string('status', 20)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });

        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('consent_scope')->nullable();
            $table->string('skip_reason')->nullable();
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10);
            $table->string('scope', 50)->default('all');
            $table->boolean('subscribed')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'channel', 'scope']);
        });

        Schema::create('push_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 2048)->unique();
            $table->string('device_id')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_devices');
        Schema::dropIfExists('notification_preferences');
        Schema::table('notification_deliveries', fn (Blueprint $table) => $table->dropConstrainedForeignId('campaign_id')->dropColumn(['consent_scope', 'skip_reason']));
        Schema::dropIfExists('campaigns');
        Schema::table('articles', fn (Blueprint $table) => $table->dropConstrainedForeignId('segment_id'));
    }
};
