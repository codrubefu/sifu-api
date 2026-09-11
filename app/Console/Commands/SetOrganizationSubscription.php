<?php

namespace App\Console\Commands;

use App\Users\Models\Organization;
use App\Users\Services\OrganizationSubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetOrganizationSubscription extends Command
{
    protected $signature = 'organization:subscription:set {organization : Organization ID}
        {--plan= : Plan code}
        {--members= : Nonnegative integer or unlimited}
        {--locations= : Nonnegative integer or unlimited}
        {--events= : Monthly occurrence limit or unlimited}
        {--administrators= : Nonnegative integer or unlimited}
        {--reset=* : Resource override to remove (repeatable)}
        {--reset-all : Remove all overrides}';
    protected $description = 'Set a plan or per-organization limits; existing data is preserved when limits decrease.';

    public function handle(OrganizationSubscriptionService $subscriptions): int
    {
        $id = $this->argument('organization');
        $organization = ctype_digit((string) $id) ? Organization::find((int) $id) : null;
        $planCode = $this->option('plan');
        $plan = $planCode === null ? null : DB::table('organization_plans')->where('code', $planCode)->first();
        $reset = $this->option('reset');
        if (! $organization || ($planCode !== null && ! $plan) || array_diff($reset, OrganizationSubscriptionService::RESOURCES)) {
            $this->error('Invalid organization, plan or reset resource.');
            return self::FAILURE;
        }
        $values = [];
        foreach (OrganizationSubscriptionService::RESOURCES as $resource) {
            $value = $this->option($resource);
            if ($value === null) { continue; }
            if ($value !== 'unlimited' && (! ctype_digit($value) || (float) $value > 2147483647)) {
                $this->error("Invalid {$resource}: use a nonnegative integer or unlimited.");
                return self::FAILURE;
            }
            if ($this->option('reset-all') || in_array($resource, $reset, true)) {
                $this->error('Do not set and reset the same resource in one command.');
                return self::FAILURE;
            }
            $values[$resource] = $value === 'unlimited' ? null : (int) $value;
        }
        DB::transaction(function () use ($organization, $plan, $reset, $values, $subscriptions): void {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $before = $subscriptions->configuration($organization->id);
            if ($plan) {
                $organization->plan_id = $plan->id;
                $organization->save();
            }
            $query = DB::table('organization_limit_overrides')->where('organization_id', $organization->id);
            if ($this->option('reset-all')) {
                $query->delete();
            } elseif ($reset !== []) {
                $query->whereIn('resource', $reset)->delete();
            }
            foreach ($values as $resource => $value) {
                $key = ['organization_id' => $organization->id, 'resource' => $resource];
                $existing = DB::table('organization_limit_overrides')->where($key)->first();
                DB::table('organization_limit_overrides')->updateOrInsert($key, ['value' => $value, 'created_at' => $existing?->created_at ?? now(), 'updated_at' => now()]);
            }
            $after = $subscriptions->configuration($organization->id);
            if ($before !== $after) {
                DB::table('audit_logs')->insert(['organization_id' => $organization->id, 'model_type' => Organization::class,
                    'model_id' => $organization->id, 'action' => 'updated', 'event_type' => 'organization.subscription.updated',
                    'old_values' => json_encode($before), 'new_values' => json_encode($after), 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        $this->line(json_encode($subscriptions->show($organization->id, now()->format('Y-m')), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
