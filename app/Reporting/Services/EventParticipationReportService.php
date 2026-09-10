<?php

namespace App\Reporting\Services;

use Illuminate\Support\Facades\DB;

class EventParticipationReportService
{
    public function aggregate(int $organizationId, array $filters): array
    {
        $query = DB::table('event_occurrences as occurrence')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->leftJoin('event_categories as category', 'category.id', '=', 'event.category_id')
            ->leftJoin('event_occurrence_user as participant', 'participant.event_occurrence_id', '=', 'occurrence.id')
            ->where('occurrence.organization_id', $organizationId)
            ->where('event.organization_id', $organizationId)
            ->whereNull('event.deleted_at')
            ->select([
                'occurrence.id', 'occurrence.occurrence_date', 'occurrence.start_datetime',
                'occurrence.end_datetime', 'event.id as event_id', 'event.title as event_title',
                'event.category_id', 'category.name as category_name',
                'event.location', 'event.max_participants',
            ])
            ->selectRaw("SUM(CASE WHEN participant.status IN ('registered', 'attended') THEN 1 ELSE 0 END) as registrations")
            ->selectRaw("SUM(CASE WHEN participant.status = 'attended' THEN 1 ELSE 0 END) as attendances")
            ->groupBy([
                'occurrence.id', 'occurrence.occurrence_date', 'occurrence.start_datetime',
                'occurrence.end_datetime', 'event.id', 'event.title', 'event.category_id',
                'category.name', 'event.location', 'event.max_participants',
            ]);

        if (isset($filters['from'])) {
            $query->whereDate('occurrence.occurrence_date', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->whereDate('occurrence.occurrence_date', '<=', $filters['to']);
        }
        if (isset($filters['category_id'])) {
            $query->where('event.category_id', $filters['category_id']);
        }
        if (isset($filters['location'])) {
            $query->where('event.location', $filters['location']);
        }
        if (isset($filters['time_from'])) {
            $query->whereTime('occurrence.start_datetime', '>=', $filters['time_from']);
        }
        if (isset($filters['time_to'])) {
            $query->whereTime('occurrence.end_datetime', '<=', $filters['time_to']);
        }

        $threshold = (float) ($filters['underutilized_below'] ?? 50);
        $groups = $query->orderBy('occurrence.occurrence_date')->orderBy('occurrence.start_datetime')->get()
            ->groupBy(fn ($row) => implode('|', [
                $row->event_id, $row->category_id ?? 'none', $row->location ?? '', $row->occurrence_date,
                substr((string) $row->start_datetime, 11, 5), substr((string) $row->end_datetime, 11, 5),
            ]))
            ->map(function ($sessions) use ($threshold): array {
                $first = $sessions->first();
                $hasUnlimitedSession = $sessions->contains(fn ($session) => $session->max_participants === null);
                $capacity = $hasUnlimitedSession ? null : (int) $sessions->sum('max_participants');
                $registrations = (int) $sessions->sum('registrations');
                $attendances = (int) $sessions->sum('attendances');
                $occupancy = $capacity === null ? null : round($registrations / $capacity * 100, 2);

                return [
                    'event' => ['id' => (int) $first->event_id, 'title' => $first->event_title],
                    'category' => ['id' => $first->category_id === null ? null : (int) $first->category_id, 'name' => $first->category_name],
                    'location' => $first->location,
                    'day' => $first->occurrence_date,
                    'time_interval' => [
                        'from' => substr((string) $first->start_datetime, 11, 5),
                        'to' => substr((string) $first->end_datetime, 11, 5),
                    ],
                    'sessions' => $sessions->count(),
                    'capacity' => $capacity,
                    'registrations' => $registrations,
                    'attendances' => $attendances,
                    'occupancy_percentage' => $occupancy,
                    'utilization' => match (true) {
                        $occupancy === null => 'capacity_not_set',
                        $occupancy >= 100 => 'full',
                        $occupancy < $threshold => 'underutilized',
                        default => 'normal',
                    },
                ];
            })->values()->all();

        return ['underutilized_below' => $threshold, 'groups' => $groups];
    }
}
