<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('notification_consents')->nullable();
            $table->string('push_token')->nullable();
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->string('event_key');
            $table->string('channel');
            $table->string('template');
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['event_key', 'user_id', 'channel']);
        });

        Schema::create('notification_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_delivery_id')->constrained()->cascadeOnDelete();
            $table->string('template');
            $table->string('channel');
            $table->string('provider');
            $table->string('external_id')->nullable();
            $table->string('status');
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempt');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_attempts');
        Schema::dropIfExists('notification_deliveries');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['notification_consents', 'push_token']));
    }
};
