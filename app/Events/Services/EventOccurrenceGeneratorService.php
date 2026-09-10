<?php

namespace App\Events\Services;

use App\Events\Models\Event;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class EventOccurrenceGeneratorService
{
    public function generateForNewEvent(Event $event): void
    {
        foreach ($this->buildOccurrences($event) as $occurrence) {
            $event->occurrences()->create($occurrence);
        }
    }

    public function regenerateFutureOpenOccurrences(Event $event): void
    {
        $event->occurrences()
            ->whereDate('occurrence_date', '>=', now()->toDateString())
            ->doesntHave('participants')
            ->delete();

        $existingDates = $event->occurrences()
            ->pluck('occurrence_date')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->all();

        foreach ($this->buildOccurrences($event) as $occurrence) {
            if (in_array($occurrence['occurrence_date'], $existingDates, true)) {
                continue;
            }

            if (CarbonImmutable::parse($occurrence['occurrence_date'])->isBefore(now()->startOfDay())) {
                continue;
            }

            $event->occurrences()->create($occurrence);
        }
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function buildOccurrences(Event $event): Collection
    {
        return match ($event->recurrence_type) {
            'once' => collect([$this->makeOccurrence($event, CarbonImmutable::parse($event->start_date))]),
            'weekly' => $this->buildWeeklyOccurrences($event),
            'monthly' => $this->buildMonthlyOccurrences($event),
            default => collect(),
        };
    }

    private function buildWeeklyOccurrences(Event $event): Collection
    {
        $days = collect($event->recurrence_days ?? [])->map(fn ($day) => strtolower($day));
        $period = CarbonPeriod::create(
            CarbonImmutable::parse($event->start_date),
            CarbonImmutable::parse($event->end_date ?? $event->start_date)->endOfDay(),
        );

        return collect($period)
            ->map(fn ($date) => CarbonImmutable::parse($date))
            ->filter(fn (CarbonImmutable $date) => $days->contains(strtolower($date->englishDayOfWeek)))
            ->map(fn (CarbonImmutable $date) => $this->makeOccurrence($event, $date))
            ->values();
    }

    private function buildMonthlyOccurrences(Event $event): Collection
    {
        $start = CarbonImmutable::parse($event->start_date)->startOfMonth();
        $end = CarbonImmutable::parse($event->end_date ?? $event->start_date)->startOfMonth();
        $occurrences = collect();

        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addMonthNoOverflow()) {
            if ($event->monthly_day > $cursor->daysInMonth) {
                continue;
            }

            $date = $cursor->setDay($event->monthly_day);

            if ($date->betweenIncluded(CarbonImmutable::parse($event->start_date), CarbonImmutable::parse($event->end_date ?? $event->start_date))) {
                $occurrences->push($this->makeOccurrence($event, $date));
            }
        }

        return $occurrences;
    }

    private function makeOccurrence(Event $event, CarbonImmutable $date): array
    {
        return [
            'occurrence_date' => $date->toDateString(),
            'start_datetime' => $date->toDateString().' '.$event->start_time,
            'end_datetime' => $date->toDateString().' '.$event->end_time,
            'status' => $event->status === 'active' ? 'scheduled' : 'cancelled',
        ];
    }
}
