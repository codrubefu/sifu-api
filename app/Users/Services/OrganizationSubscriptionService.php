<?php

namespace App\Users\Services;

use App\Users\Exceptions\OrganizationLimitExceeded;
use Illuminate\Support\Facades\DB;

class OrganizationSubscriptionService
{
    public const RESOURCES = ['members', 'locations', 'events', 'administrators'];

    private array $guarded = [];

    public function configuration(int $organizationId): array
    {
        $organization = DB::table('organizations')->where('id', $organizationId)->first();
        abort_unless($organization, 404);
        $plan = DB::table('organization_plans')->where('id', $organization->plan_id)->first();
        abort_unless($plan, 503, 'Organization subscription configuration is missing.');
        $overrides = DB::table('organization_limit_overrides')->where('organization_id', $organizationId)->get()->keyBy('resource');
        $limits = [];
        foreach (self::RESOURCES as $resource) {
            $value = $overrides->has($resource) ? $overrides[$resource]->value : $plan->{$resource};
            $limits[$resource] = ['limit' => $value === null ? null : (int) $value, 'source' => $overrides->has($resource) ? 'override' : 'plan'];
        }

        return ['plan' => ['code' => $plan->code, 'name' => $plan->name, 'monthly_price_cents' => (int) $plan->monthly_price_cents, 'currency' => $plan->currency], 'limits' => $limits];
    }

    public function usage(int $organizationId): array
    {
        // Raw, explicitly tenant-scoped queries intentionally ignore operator location visibility.
        $users = DB::table('users')->where('organization_id', $organizationId)->where('active', true);
        $admins = (clone $users)->whereExists(function ($query): void {
            $query->selectRaw('1')->from('group_user')
                ->join('groups', 'groups.id', '=', 'group_user.group_id')
                ->join('group_right', 'group_right.group_id', '=', 'groups.id')
                ->join('rights', 'rights.id', '=', 'group_right.right_id')
                ->whereColumn('group_user.user_id', 'users.id')
                ->whereColumn('groups.organization_id', 'users.organization_id')
                ->where('rights.name', '!=', 'profile.view');
        })->count();
        $events = DB::table('event_occurrences')->where('organization_id', $organizationId)
            ->whereIn('status', ['scheduled', 'completed'])
            ->selectRaw('SUBSTR(occurrence_date, 1, 7) as period, COUNT(*) as total')
            ->groupByRaw('SUBSTR(occurrence_date, 1, 7)')->pluck('total', 'period')->map(fn ($value) => (int) $value)->all();

        return ['members' => $users->count() - $admins, 'administrators' => $admins,
            'locations' => DB::table('locations')->where('organization_id', $organizationId)->count(), 'events' => $events];
    }

    public function show(int $organizationId, string $month): array
    {
        $result = $this->configuration($organizationId);
        $usage = $this->usage($organizationId);
        foreach (self::RESOURCES as $resource) {
            $used = $resource === 'events' ? ($usage['events'][$month] ?? 0) : $usage[$resource];
            $limit = $result['limits'][$resource]['limit'];
            $result['limits'][$resource] += ['used' => $used, 'remaining' => $limit === null ? null : max(0, $limit - $used), 'exceeded' => $limit !== null && $used > $limit];
        }
        $result['month'] = $month;
        $result['event_usage_basis'] = 'generated_occurrences';
        $result['event_generation_blocks'] = DB::table('organization_event_limit_blocks')->join('events', 'events.id', '=', 'organization_event_limit_blocks.event_id')
            ->where('organization_event_limit_blocks.organization_id', $organizationId)->where('events.status', 'active')->whereNull('events.deleted_at')
            ->orderBy('event_id')->get(['event_id', 'details'])->map(fn ($row) => ['event_id' => $row->event_id, 'details' => json_decode($row->details, true)])->all();

        return $result;
    }

    public function check(int $organizationId, string $resource, int $quantity, ?string $month): array
    {
        $limit = $this->configuration($organizationId)['limits'][$resource]['limit'];
        $usage = $this->usage($organizationId);
        $used = $resource === 'events' ? ($usage['events'][$month] ?? 0) : $usage[$resource];
        $allowed = $limit === null || $used + $quantity <= $limit;

        return ['allowed' => $allowed] + $this->details($resource, $limit, $used, $used + $quantity, $month, $allowed);
    }

    /** Serialize quota-affecting writes and validate their final net usage before commit. */
    public function guard(array $organizationIds, callable $operation): mixed
    {
        $ids = array_values(array_diff(array_unique(array_filter($organizationIds)), $this->guarded));
        sort($ids, SORT_NUMERIC);
        if ($ids === []) {
            return $operation();
        }

        return DB::transaction(function () use ($ids, $operation) {
            DB::table('organizations')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $before = [];
            foreach ($ids as $id) {
                $before[$id] = $this->usage($id);
            }
            $previous = $this->guarded;
            $this->guarded = array_merge($previous, $ids);
            try {
                $result = $operation();
                foreach ($ids as $id) {
                    $after = $this->usage($id);
                    $limits = $this->configuration($id)['limits'];
                    foreach (self::RESOURCES as $resource) {
                        $periods = $resource === 'events' ? array_keys($after['events']) : [null];
                        foreach ($periods as $period) {
                            $used = $resource === 'events' ? ($before[$id]['events'][$period] ?? 0) : $before[$id][$resource];
                            $projected = $resource === 'events' ? $after['events'][$period] : $after[$resource];
                            $limit = $limits[$resource]['limit'];
                            if ($limit !== null && $projected > $limit && $projected > $used) {
                                throw new OrganizationLimitExceeded($this->details($resource, $limit, $used, $projected, $period));
                            }
                        }
                    }
                }

                return $result;
            } finally {
                $this->guarded = $previous;
            }
        });
    }

    private function details(string $resource, ?int $limit, int $used, int $projected, ?string $period, bool $allowed = false): array
    {
        $labels = ['members' => 'membri activi', 'locations' => 'locații', 'events' => 'apariții de evenimente', 'administrators' => 'administratori'];

        return ['message' => $allowed ? 'Limita permite operația.' : 'Limita de '.$labels[$resource].' a organizației a fost atinsă.',
            'code' => $allowed ? 'organization_limit_available' : 'organization_limit_exceeded', 'resource' => $resource,
            'limit' => $limit, 'used' => $used, 'requested' => $projected - $used, 'projected' => $projected, 'period' => $resource === 'events' ? $period : null];
    }
}
