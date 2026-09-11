<?php

namespace Tests\Feature\Events;

use App\Events\Jobs\ExtendRecurringEventOccurrences;
use App\Events\Models\Event;
use App\Events\Services\EventOccurrenceGeneratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventOccurrenceRollingWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_recurring_events_are_created_only_through_the_rolling_horizon(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00');
        $event = $this->event(['start_date' => '2026-09-14', 'recurrence_days' => ['monday']]);

        app(EventOccurrenceGeneratorService::class)->generateForNewEvent($event);

        $event->refresh();
        $this->assertSame('2026-11-11', $event->occurrences_generated_until->toDateString());
        $this->assertDatabaseHas('event_occurrences', ['event_id' => $event->id, 'occurrence_date' => '2026-11-09']);
        $this->assertDatabaseMissing('event_occurrences', ['event_id' => $event->id, 'occurrence_date' => '2026-11-16']);
    }

    public function test_end_date_caps_the_horizon_and_the_daily_job_extends_it_idempotently(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00');
        $event = $this->event(['start_date' => '2026-09-14', 'end_date' => '2027-03-31', 'recurrence_days' => ['monday']]);
        $generator = app(EventOccurrenceGeneratorService::class);
        $generator->generateForNewEvent($event);

        Carbon::setTestNow('2026-10-11 10:00:00');
        app(ExtendRecurringEventOccurrences::class)->handle($generator);
        $event->refresh();
        $this->assertSame('2026-12-11', $event->occurrences_generated_until->toDateString());
        $count = $event->occurrences()->count();

        app(ExtendRecurringEventOccurrences::class)->handle($generator);
        $this->assertSame($count, $event->occurrences()->count());
    }

    public function test_legacy_and_completed_horizon_events_are_ignored_by_the_job(): void
    {
        Carbon::setTestNow('2026-09-11 10:00:00');
        $legacy = $this->event(['occurrences_generated_until' => null]);
        $completed = $this->event(['end_date' => '2026-10-01', 'occurrences_generated_until' => '2026-10-01']);
        $generator = app(EventOccurrenceGeneratorService::class);

        app(ExtendRecurringEventOccurrences::class)->handle($generator);

        $this->assertSame(0, $legacy->occurrences()->count());
        $this->assertSame(0, $completed->occurrences()->count());
    }

    private function event(array $overrides = []): Event
    {
        return Event::query()->create(array_merge([
            'title' => 'Rolling window event',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'recurrence_type' => 'weekly',
            'recurrence_days' => ['monday'],
            'start_date' => '2026-09-14',
            'status' => 'active',
        ], $overrides));
    }
}
