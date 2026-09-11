<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('monthly_price_cents');
            $table->string('currency', 3)->default('EUR');
            foreach (['members', 'locations', 'events', 'administrators'] as $resource) {
                $table->unsignedInteger($resource)->nullable();
            }
            $table->timestamps();
        });
        foreach ([['start', 'Start', 3000, 50, 1, 30, 1], ['plus', 'Plus', 6000, 150, 3, 100, 2], ['pro', 'Pro', 10000, 500, 10, null, null]] as $values) {
            DB::table('organization_plans')->insert(array_combine(['code', 'name', 'monthly_price_cents', 'members', 'locations', 'events', 'administrators'], $values) + ['currency' => 'EUR', 'created_at' => now(), 'updated_at' => now()]);
        }
        $startId = DB::table('organization_plans')->where('code', 'start')->value('id');
        Schema::table('organizations', function (Blueprint $table) use ($startId): void {
            $table->foreignId('plan_id')->default($startId)->constrained('organization_plans')->restrictOnDelete();
        });
        Schema::create('organization_limit_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->enum('resource', ['members', 'locations', 'events', 'administrators']);
            $table->unsignedInteger('value')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'resource']);
        });
        Schema::create('organization_event_limit_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('details');
            $table->timestamps();
        });
        $rightId = DB::table('rights')->where('name', 'organization_subscription.view')->value('id');
        if (! $rightId) {
            $rightId = DB::table('rights')->insertGetId(['name' => 'organization_subscription.view', 'label' => 'View organization subscription', 'description' => 'Read organization limits and usage.', 'created_at' => now(), 'updated_at' => now()]);
        }
        $groups = DB::table('group_right')->join('rights', 'rights.id', '=', 'group_right.right_id')->where('rights.name', '!=', 'profile.view')->distinct()->pluck('group_id');
        foreach ($groups as $groupId) {
            DB::table('group_right')->insertOrIgnore(['group_id' => $groupId, 'right_id' => $rightId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('group_right')->whereIn('right_id', DB::table('rights')->select('id')->where('name', 'organization_subscription.view'))->delete();
        DB::table('rights')->where('name', 'organization_subscription.view')->delete();
        Schema::dropIfExists('organization_event_limit_blocks');
        Schema::dropIfExists('organization_limit_overrides');
        Schema::table('organizations', fn (Blueprint $table) => $table->dropConstrainedForeignId('plan_id'));
        Schema::dropIfExists('organization_plans');
    }
};
