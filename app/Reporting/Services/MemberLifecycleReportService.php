<?php

namespace App\Reporting\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Builds membership lifecycle metrics from the immutable service_user history.
 *
 * The precise business definitions (including boundary conditions) live in
 * docs/reporting/member-lifecycle.md and are kept separate from API wording.
 */
class MemberLifecycleReportService
{
    private const METRICS = [
        'new_members',
        'eligible_for_renewal',
        'renewed',
        'not_renewed',
        'reactivated',
    ];

    /**
     * @param array{from:string,to:string,group_by?:string|array<int, string>} $filters
     * @return array{from:string,to:string,group_by:array<int, string>,totals:array<string, int>,rows:array<int, array<string, mixed>>}
     */
    public function report(int $organizationId, array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $groups = $this->groups($filters['group_by'] ?? []);
        $assignments = $this->assignments($organizationId);
        $locations = $this->locationsByUser($assignments);
        $events = collect();

        $assignments->groupBy(fn (object $row): string => $row->user_id.'|'.$row->service_type)
            ->each(function (Collection $history) use ($events): void {
                $history = $history->sortBy(fn (object $row): string => sprintf(
                    '%s-%020d',
                    $this->startedAt($row)->format('Y-m-d H:i:s.u'),
                    $row->id,
                ))->values();

                foreach ($history as $index => $assignment) {
                    $start = $this->startedAt($assignment);
                    if ($index === 0) {
                        $events->push($this->event('new_members', $start, $assignment));
                    }

                    if ($index > 0) {
                        $previous = $history[$index - 1];
                        if ($previous->expires_at !== null) {
                            $deadline = CarbonImmutable::parse($previous->expires_at)->addDays((int) $previous->grace_period_days);
                            $events->push($this->event(
                                $start->lessThanOrEqualTo($deadline) ? 'renewed' : 'reactivated',
                                $start,
                                $assignment,
                            ));
                        }
                    }

                    if ($assignment->expires_at === null) {
                        continue;
                    }

                    $expiry = CarbonImmutable::parse($assignment->expires_at);
                    $events->push($this->event('eligible_for_renewal', $expiry, $assignment));
                    $next = $history[$index + 1] ?? null;
                    $deadline = $expiry->addDays((int) $assignment->grace_period_days);
                    if ($next === null || $this->startedAt($next)->greaterThan($deadline)) {
                        $events->push($this->event('not_renewed', $deadline, $assignment));
                    }
                }
            });

        $events = $events->filter(fn (array $event): bool => $event['date']->betweenIncluded($from, $to));
        $rows = [];
        foreach ($events as $event) {
            foreach ($this->dimensions($event, $groups, $locations) as $dimensions) {
                $key = json_encode($dimensions, JSON_THROW_ON_ERROR);
                $rows[$key] ??= array_merge($dimensions, array_fill_keys(self::METRICS, 0));
                $rows[$key][$event['metric']]++;
            }
        }

        $rows = collect(array_values($rows))->sortBy(fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR))->values()->all();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'group_by' => $groups,
            'totals' => collect(self::METRICS)->mapWithKeys(fn (string $metric): array => [$metric => $events->where('metric', $metric)->count()])->all(),
            'rows' => $rows,
        ];
    }

    public function aggregate(int $organizationId, array $filters): array
    {
        return $this->report($organizationId, $filters);
    }

    private function assignments(int $organizationId): Collection
    {
        return DB::table('service_user as su')
            ->join('services as service', 'service.id', '=', 'su.service_id')
            ->where('service.organization_id', $organizationId)
            ->whereNotNull(DB::raw('COALESCE(su.start_date, su.activated_at, su.created_at)'))
            ->get([
                'su.id', 'su.user_id', 'su.start_date', 'su.activated_at', 'su.created_at', 'su.expires_at',
                'service.type as service_type', 'service.grace_period_days',
            ]);
    }

    private function locationsByUser(Collection $assignments): Collection
    {
        $userIds = $assignments->pluck('user_id')->unique()->values();
        if ($userIds->isEmpty()) {
            return collect();
        }

        return DB::table('location_user as membership')
            ->join('locations as location', 'location.id', '=', 'membership.location_id')
            ->whereIn('membership.user_id', $userIds)
            ->get(['membership.user_id', 'location.id', 'location.name'])
            ->groupBy('user_id');
    }

    private function event(string $metric, CarbonImmutable $date, object $assignment): array
    {
        return ['metric' => $metric, 'date' => $date, 'user_id' => $assignment->user_id, 'service_type' => $assignment->service_type];
    }

    private function dimensions(array $event, array $groups, Collection $locations): array
    {
        $base = [];
        if (in_array('month', $groups, true)) {
            $base['month'] = $event['date']->format('Y-m');
        }
        if (in_array('service_type', $groups, true)) {
            $base['service_type'] = $event['service_type'];
        }
        if (! in_array('location', $groups, true)) {
            return [$base];
        }

        $memberLocations = $locations->get($event['user_id'], collect());
        if ($memberLocations->isEmpty()) {
            return [array_merge($base, ['location_id' => null, 'location_name' => null])];
        }

        return $memberLocations->map(fn (object $location): array => array_merge($base, [
            'location_id' => (int) $location->id,
            'location_name' => $location->name,
        ]))->all();
    }

    private function startedAt(object $assignment): CarbonImmutable
    {
        return CarbonImmutable::parse($assignment->start_date ?? $assignment->activated_at ?? $assignment->created_at);
    }

    private function period(array $filters): array
    {
        if (! isset($filters['from'], $filters['to'])) {
            throw new InvalidArgumentException('The from and to dates are required.');
        }

        $from = CarbonImmutable::parse($filters['from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['to'])->endOfDay();
        if ($from->greaterThan($to)) {
            throw new InvalidArgumentException('The from date must be before or equal to the to date.');
        }

        return [$from, $to];
    }

    private function groups(string|array $groups): array
    {
        $groups = is_string($groups) ? array_filter(array_map('trim', explode(',', $groups))) : $groups;
        $groups = array_values(array_unique($groups));
        $invalid = array_diff($groups, ['month', 'location', 'service_type']);
        if ($invalid !== []) {
            throw new InvalidArgumentException('Unsupported grouping: '.implode(', ', $invalid).'.');
        }

        return $groups;
    }
}
