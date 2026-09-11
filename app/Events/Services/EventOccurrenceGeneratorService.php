<?php

namespace App\Events\Services;

use App\Events\Models\Event;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class EventOccurrenceGeneratorService
{
    public const ROLLING_WINDOW_MONTHS = 2;

    public function generateForNewEvent(Event $event): void
    {
        app(\App\Users\Services\OrganizationSubscriptionService::class)->guard([$event->organization_id], fn () => $this->generateForNewEventUnchecked($event));
    }

    private function generateForNewEventUnchecked(Event $event): void
    {
        $horizon = $this->resolveGenerationHorizon($event);
        $this->generateOccurrences($event, $horizon ?? Carbon::parse($event->start_date));

        if ($horizon !== null) {
            $event->occurrences_generated_until = $horizon;
            $event->save();
        }
    }

    public function regenerateFutureOpenOccurrences(Event $event): void
    {
        app(\App\Users\Services\OrganizationSubscriptionService::class)->guard([$event->organization_id], fn () => $this->regenerateFutureOpenOccurrencesUnchecked($event));
    }

    private function regenerateFutureOpenOccurrencesUnchecked(Event $event): void
    {
        $event->occurrences()
            ->whereDate('occurrence_date', '>=', now()->toDateString())
            ->doesntHave('participants')
            ->delete();

        $existingDates = $event->occurrences()
            ->pluck('occurrence_date')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->all();

        $horizon = $this->resolveGenerationHorizon($event);

        if ($horizon === null) {
            $this->generateOccurrences($event, Carbon::parse($event->start_date), null, $existingDates, true);
            $event->occurrences_generated_until = null;
            $event->save();

            return;
        }

        $this->generateOccurrences($event, $horizon, null, $existingDates, true);
        $event->occurrences_generated_until = $horizon;
        $event->save();
    }

    public function resolveGenerationHorizon(Event $event, ?Carbon $referenceDate = null): ?Carbon
    {
        if (! in_array($event->recurrence_type, ['weekly', 'monthly'], true)) {
            return null;
        }

        $rollingTarget = ($referenceDate ?? Carbon::now())->copy()->addMonths(self::ROLLING_WINDOW_MONTHS);

        if ($event->end_date === null) {
            return $rollingTarget;
        }

        $endDate = Carbon::parse($event->end_date);

        return $endDate->lessThan($rollingTarget) ? $endDate : $rollingTarget;
    }

    public function generateOccurrences(Event $event, Carbon $horizon, ?Carbon $after = null, array $existingDates = [], bool $futureOnly = false): void
    {
        app(\App\Users\Services\OrganizationSubscriptionService::class)->guard([$event->organization_id], fn () => $this->generateOccurrencesUnchecked($event, $horizon, $after, $existingDates, $futureOnly));
    }

    private function generateOccurrencesUnchecked(Event $event, Carbon $horizon, ?Carbon $after = null, array $existingDates = [], bool $futureOnly = false): void
    {
        foreach ($this->buildOccurrences($event, $horizon) as $occurrence) {
            $date = CarbonImmutable::parse($occurrence['occurrence_date']);

            if (($after !== null && $date->lessThanOrEqualTo($after->toImmutable()->startOfDay()))
                || in_array($occurrence['occurrence_date'], $existingDates, true)
                || ($futureOnly && $date->isBefore(now()->startOfDay()))) {
                continue;
            }

            $event->occurrences()->firstOrCreate(
                ['occurrence_date' => $occurrence['occurrence_date']],
                $occurrence,
            );
        }
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function buildOccurrences(Event $event, Carbon $horizon): Collection
    {
        return match ($event->recurrence_type) {
            'once' => collect([$this->makeOccurrence($event, CarbonImmutable::parse($event->start_date))]),
            'weekly' => $this->buildWeeklyOccurrences($event, $horizon),
            'monthly' => $this->buildMonthlyOccurrences($event, $horizon),
            default => collect(),
        };
    }

    private function buildWeeklyOccurrences(Event $event, Carbon $horizon): Collection
    {
        $days = collect($event->recurrence_days ?? [])->map(fn ($day) => strtolower($day));
        $period = CarbonPeriod::create(
            CarbonImmutable::parse($event->start_date),
            CarbonImmutable::parse($horizon)->endOfDay(),
        );

        return collect($period)
            ->map(fn ($date) => CarbonImmutable::parse($date))
            ->filter(fn (CarbonImmutable $date) => $days->contains(strtolower($date->englishDayOfWeek)))
            ->map(fn (CarbonImmutable $date) => $this->makeOccurrence($event, $date))
            ->values();
    }

    private function buildMonthlyOccurrences(Event $event, Carbon $horizon): Collection
    {
        $start = CarbonImmutable::parse($event->start_date)->startOfMonth();
        $end = CarbonImmutable::parse($horizon)->startOfMonth();
        $occurrences = collect();

        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addMonthNoOverflow()) {
            if ($event->monthly_day > $cursor->daysInMonth) {
                continue;
            }

            $date = $cursor->setDay($event->monthly_day);

            if ($date->betweenIncluded(CarbonImmutable::parse($event->start_date), CarbonImmutable::parse($horizon))) {
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
            'organization_id' => $event->organization_id,
        ];
    }
}
