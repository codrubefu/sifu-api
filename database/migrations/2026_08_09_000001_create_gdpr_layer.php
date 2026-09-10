<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 100);
            $table->string('channel', 32);
            $table->string('policy_version', 40);
            $table->boolean('granted');
            $table->timestamp('occurred_at');
            $table->string('source', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'user_id', 'purpose', 'channel', 'occurred_at'], 'consent_current_lookup');
        });

        Schema::create('gdpr_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->string('status', 32)->default('pending');
            $table->json('details')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('subject_fingerprint', 64)->nullable();
            $table->json('execution_proof')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'type']);
        });

        Schema::create('gdpr_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('gdpr_request_id')->constrained('gdpr_requests')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('disk', 32)->default('local');
            $table->string('path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'user_id']);
        });

        // Preserve the history starting point when upgrading installations that used the JSON snapshot.
        DB::table('users')->whereNotNull('notification_consents')->orderBy('id')->each(function (object $user): void {
            foreach ((array) json_decode($user->notification_consents, true) as $channel => $granted) {
                DB::table('consent_records')->insert([
                    'organization_id' => $user->organization_id, 'user_id' => $user->id,
                    'purpose' => 'notifications', 'channel' => $channel, 'policy_version' => 'legacy',
                    'granted' => (bool) $granted, 'occurred_at' => $user->updated_at ?? now(),
                    'source' => 'legacy_migration', 'actor_id' => null, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_exports');
        Schema::dropIfExists('gdpr_requests');
        Schema::dropIfExists('consent_records');
    }
};
