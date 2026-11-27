<?php

namespace Tests\Feature;

use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Payments\Models\Payment;
use App\Reporting\Models\Segment;
use App\Service\Models\Service;
use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Paid-event participations as payment obligations in financial reports and
 * documents (event invoices, receipt descriptions).
 */
class FinancialReportingEventsTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private string $token;

    private Service $service;

    private Event $event;

    private int $paidParticipation;

    private int $partialParticipation;

    private int $serviceAssignment;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-29 12:00:00');

        [$this->operator, $this->token] = $this->loginWith(['reports.view', 'reports.export']);
        $organizationId = $this->operator->organization_id;

        $ana = User::factory()->create(['organization_id' => $organizationId, 'first_name' => 'Ana', 'last_name' => 'Pop']);
        $bogdan = User::factory()->create(['organization_id' => $organizationId, 'first_name' => 'Bogdan', 'last_name' => 'Ion']);
        $cancelled = User::factory()->create(['organization_id' => $organizationId, 'active' => false]);

        $this->service = Service::query()->create([
            'organization_id' => $organizationId, 'name' => 'Abonament', 'description' => 'x', 'type' => 'membership',
            'price' => 50, 'currency' => 'RON', 'max_users' => 20, 'is_active' => true,
        ]);
        $this->service->users()->attach($ana);
        $this->serviceAssignment = (int) DB::table('service_user')->where('user_id', $ana->id)->value('id');
        DB::table('service_user')->where('id', $this->serviceAssignment)->update(['created_at' => '2026-08-01 09:00:00']);

        $this->event = $this->event($organizationId, ['title' => 'Seminar']);
        $occurrence = $this->occurrence($this->event, '2026-07-20');
        $this->paidParticipation = $this->participate($occurrence, $ana, 'registered', ['invoice_number' => 'INV000001', 'invoiced_at' => '2026-08-05 10:00:00']);
        $this->partialParticipation = $this->participate($occurrence, $bogdan, 'attended');
        $this->participate($occurrence, $cancelled, 'cancelled');

        // Not obligations: cancelled occurrence, unpaid event, other organization.
        $this->participate($this->occurrence($this->event, '2026-07-27', 'cancelled'), $ana, 'registered');
        $free = $this->event($organizationId, ['title' => 'Free', 'requires_payment' => false, 'payment_amount' => null, 'payment_type' => null]);
        $this->participate($this->occurrence($free, '2026-07-20'), $ana, 'registered');
        $otherOrganization = Organization::factory()->create();
        $foreign = $this->event($otherOrganization->id, ['title' => 'Foreign']);
        $this->participate($this->occurrence($foreign, '2026-07-20'), User::factory()->create(['organization_id' => $otherOrganization->id]), 'registered');

        $this->payment($this->paidParticipation, 100, 'REC000001', '2026-08-06 10:00:00');
        $this->payment($this->partialParticipation, 30, null, '2026-08-07 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_receivables_totals_include_event_obligations_and_payments(): void
    {
        $this->withToken($this->token)->getJson('/api/reports/financial')
            ->assertOk()
            ->assertJsonPath('data.receivables.invoiced', 250)
            ->assertJsonPath('data.receivables.paid', 130)
            ->assertJsonPath('data.receivables.outstanding', 120)
            ->assertJsonPath('data.receivables.by_type.service.invoiced', 50)
            ->assertJsonPath('data.receivables.by_type.service.paid', 0)
            ->assertJsonPath('data.receivables.by_type.event.invoiced', 200)
            ->assertJsonPath('data.receivables.by_type.event.paid', 130)
            ->assertJsonPath('data.receivables.by_type.event.outstanding', 70);

        $this->withToken($this->token)->getJson('/api/reports/financial?service_id='.$this->service->id)
            ->assertOk()
            ->assertJsonPath('data.receivables.invoiced', 50)
            ->assertJsonPath('data.receivables.paid', 0)
            ->assertJsonPath('data.receivables.by_type.event.invoiced', 0);
    }

    public function test_segment_filter_keeps_event_payments(): void
    {
        $segment = Segment::query()->create(['organization_id' => $this->operator->organization_id, 'created_by' => $this->operator->id, 'name' => 'Active', 'criteria' => ['active' => true]]);

        $this->withToken($this->token)->getJson('/api/reports/financial?segment_id='.$segment->id)
            ->assertOk()
            ->assertJsonPath('data.totals.confirmed', 130)
            ->assertJsonPath('data.receivables.by_type.event.invoiced', 200);
    }

    public function test_receivable_rows_include_aged_event_obligations(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/reports/financial/receivables')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $rows = collect($response->json('data'))->keyBy(fn ($row) => $row['obligation_type'].':'.$row['obligation_id']);
        $service = $rows['service:'.$this->serviceAssignment];
        $this->assertNull($service['event_id']);
        $this->assertSame($this->service->id, $service['service_id']);

        $paid = $rows['event:'.$this->paidParticipation];
        $this->assertSame('INV000001', $paid['invoice_number']);
        $this->assertSame($this->event->id, $paid['event_id']);
        $this->assertSame('Seminar', $paid['event_title']);
        $this->assertNull($paid['service_id']);
        $this->assertSame('EUR', $paid['currency']);
        $this->assertEquals(0, $paid['balance']);
        $this->assertNull($paid['aging_bucket']);

        $partial = $rows['event:'.$this->partialParticipation];
        $this->assertEquals(100, $partial['invoiced_amount']);
        $this->assertEquals(30, $partial['paid_amount']);
        $this->assertEquals(70, $partial['balance']);
        $this->assertSame(40, $partial['days_overdue']);
        $this->assertSame('31-60', $partial['aging_bucket']);

        $this->withToken($this->token)->getJson('/api/reports/financial/receivables?service_type=membership')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.obligation_type', 'service');
        $this->withToken($this->token)->getJson('/api/reports/financial/receivables?from=2026-07-01&to=2026-07-31')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.obligation_type', 'event');
    }

    public function test_service_breakdown_includes_event_rows(): void
    {
        $this->withToken($this->token)->getJson('/api/reports/financial?group_by=service')
            ->assertOk()
            ->assertJsonCount(2, 'data.revenue_by_service')
            ->assertJsonPath('data.revenue_by_service.0.obligation_type', 'service')
            ->assertJsonPath('data.revenue_by_service.1.obligation_type', 'event')
            ->assertJsonPath('data.revenue_by_service.1.service_type', 'event')
            ->assertJsonPath('data.revenue_by_service.1.event_id', $this->event->id)
            ->assertJsonPath('data.revenue_by_service.1.event_title', 'Seminar')
            ->assertJsonPath('data.revenue_by_service.1.subscriptions', 2)
            ->assertJsonPath('data.revenue_by_service.1.invoiced', 200)
            ->assertJsonPath('data.revenue_by_service.1.confirmed', 130)
            ->assertJsonPath('data.revenue_by_service.1.outstanding', 70)
            ->assertJsonMissingPath('data.revenue_by_service.1.service_id');

        $this->withToken($this->token)->getJson('/api/reports/financial?group_by=service_type')
            ->assertOk()
            ->assertJsonPath('data.revenue_by_service_type.1.service_type', 'event')
            ->assertJsonPath('data.revenue_by_service_type.1.invoiced', 200)
            ->assertJsonMissingPath('data.revenue_by_service_type.1.event_id');

        $this->withToken($this->token)->getJson('/api/reports/financial?group_by=service&service_id='.$this->service->id)
            ->assertOk()->assertJsonCount(1, 'data.revenue_by_service');
    }

    public function test_financial_documents_list_and_download_event_invoices(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/reports/financial-documents?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'event_invoice',
                'number' => 'INV000001',
                'source' => 'event',
                'description' => 'Eveniment Seminar - 20.07.2026',
                'currency' => 'EUR',
                'xml_filename' => 'efactura-INV000001.xml',
            ])
            ->assertJsonFragment([
                'type' => 'receipt',
                'number' => 'REC000001',
                'source' => 'event',
                'description' => 'Eveniment Seminar - 20.07.2026',
                'currency' => 'EUR',
            ]);
        $this->assertCount(2, $response->json('data'));

        $this->withToken($this->token)
            ->get("/api/reports/financial-documents/event_invoice/{$this->paidParticipation}/download")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->withToken($this->token)
            ->get("/api/reports/financial-documents/event_invoice/{$this->paidParticipation}/download/xml")
            ->assertOk()->assertHeader('Content-Type', 'application/xml');
        $this->withToken($this->token)
            ->get("/api/reports/financial-documents/event_invoice/{$this->partialParticipation}/download")
            ->assertNotFound();
        $this->withToken($this->token)
            ->get('/api/reports/financial-documents/download?from=2026-08-01&to=2026-08-31')
            ->assertOk()->assertHeader('Content-Type', 'application/zip');
    }

    public function test_event_invoice_of_another_organization_cannot_be_downloaded(): void
    {
        $otherOrganization = Organization::factory()->create();
        $foreign = $this->event($otherOrganization->id, ['title' => 'Foreign paid']);
        $participation = $this->participate(
            $this->occurrence($foreign, '2026-07-20'),
            User::factory()->create(['organization_id' => $otherOrganization->id]),
            'registered',
            ['invoice_number' => 'INV000009', 'invoiced_at' => '2026-08-05 10:00:00'],
        );

        $this->withToken($this->token)
            ->get("/api/reports/financial-documents/event_invoice/{$participation}/download")
            ->assertNotFound();
        $this->withToken($this->token)
            ->getJson('/api/reports/financial-documents')
            ->assertOk()
            ->assertJsonMissing(['number' => 'INV000009']);
    }

    private function event(int $organizationId, array $overrides = []): Event
    {
        return Event::query()->create(array_merge([
            'organization_id' => $organizationId,
            'title' => 'Event',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'recurrence_type' => 'once',
            'start_date' => '2026-07-20',
            'requires_active_service' => false,
            'requires_payment' => true,
            'payment_amount' => 100,
            'payment_type' => 'EUR',
            'status' => 'active',
        ], $overrides));
    }

    private function occurrence(Event $event, string $date, string $status = 'scheduled'): EventOccurrence
    {
        return EventOccurrence::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'occurrence_date' => $date,
            'start_datetime' => $date.' 10:00:00',
            'end_datetime' => $date.' 11:00:00',
            'status' => $status,
        ]);
    }

    private function participate(EventOccurrence $occurrence, User $user, string $status, array $extra = []): int
    {
        return DB::table('event_occurrence_user')->insertGetId(array_merge([
            'event_occurrence_id' => $occurrence->id,
            'user_id' => $user->id,
            'status' => $status,
            'registered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    private function payment(int $participationId, float $amount, ?string $receipt, string $paidAt): void
    {
        Payment::query()->create([
            'organization_id' => $this->operator->organization_id,
            'first_name' => 'Test', 'last_name' => 'Member',
            'payment_type_id' => Payment::TYPE_CASH,
            'status' => Payment::STATUS_CONFIRMED,
            'receipt_number' => $receipt,
            'model_type' => Payment::MODEL_TYPE_EVENT_OCCURRENCE_USER,
            'model_id' => $participationId,
            'amount' => $amount,
            'paid_at' => $paidAt,
            'admin_id' => $this->operator->id,
        ]);
    }

    private function loginWith(array $rightNames): array
    {
        $user = User::factory()->create(['password' => 'password']);
        $group = Group::query()->create(['name' => fake()->unique()->slug(), 'label' => 'Reports']);
        foreach ($rightNames as $rightName) {
            $group->rights()->attach(Right::query()->create(['name' => $rightName, 'label' => $rightName]));
        }
        $user->groups()->attach($group);
        $token = $this->postJson('/api/login', ['email' => $user->email, 'organization_id' => $user->organization_id, 'password' => 'password'])->json('token');

        return [$user, $token];
    }
}
