<?php

namespace Tests\Feature\Events;

use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Payments\Models\Payment;
use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventParticipantInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_invoice_once_and_downloads_pdf_and_xml(): void
    {
        [$admin, $token] = $this->loginWith(['event_participants.manage']);
        [$occurrence, $member, $pivotId] = $this->participation($admin->organization_id);

        $this->withToken($token)
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants/{$member->id}/invoice")
            ->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV000001')
            ->assertJsonPath('data.pivot_id', $pivotId)
            ->assertJsonPath('data.payment', null);

        $this->withToken($token)
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants/{$member->id}/invoice")
            ->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV000001');

        $this->assertDatabaseHas('organizations', ['id' => $admin->organization_id, 'invoice_number' => 1]);
        $this->assertNotNull(DB::table('event_occurrence_user')->where('id', $pivotId)->value('invoiced_at'));

        $this->withToken($token)
            ->get("/api/event-occurrences/{$occurrence->id}/participants/{$member->id}/invoice")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $xml = $this->withToken($token)
            ->get("/api/event-occurrences/{$occurrence->id}/participants/{$member->id}/invoice/xml")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->getContent();
        $this->assertStringContainsString('<cbc:ID>INV000001</cbc:ID>', $xml);
        $this->assertStringContainsString('currencyID="EUR">49.99<', $xml);
        $this->assertStringContainsString('Eveniment Seminar - 10.06.2026', $xml);
    }

    public function test_service_and_event_invoices_share_the_organization_sequence(): void
    {
        [$admin, $token] = $this->loginWith(['event_participants.manage']);
        DB::table('organizations')->where('id', $admin->organization_id)->update(['invoice_code' => 'SF', 'invoice_number' => 7]);
        [$occurrence, $member] = $this->participation($admin->organization_id);

        $this->withToken($token)
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants/{$member->id}/invoice")
            ->assertOk()
            ->assertJsonPath('data.invoice_number', 'SF000008');
    }

    public function test_list_exposes_invoice_and_latest_confirmed_payment(): void
    {
        [$admin, $token] = $this->loginWith(['event_participants.view']);
        [$occurrence, $member, $pivotId] = $this->participation($admin->organization_id);
        DB::table('event_occurrence_user')->where('id', $pivotId)->update(['invoice_number' => 'INV000003']);
        $payment = Payment::query()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Ana', 'last_name' => 'Pop',
            'payment_type_id' => Payment::TYPE_CASH,
            'status' => Payment::STATUS_CONFIRMED,
            'receipt_number' => 'REC000004',
            'model_type' => Payment::MODEL_TYPE_EVENT_OCCURRENCE_USER,
            'model_id' => $pivotId,
            'amount' => 49.99,
            'paid_at' => '2026-06-09 10:00:00',
            'admin_id' => $admin->id,
        ]);

        $this->withToken($token)
            ->getJson("/api/event-occurrences/{$occurrence->id}/participants")
            ->assertOk()
            ->assertJsonPath('data.0.invoice_number', 'INV000003')
            ->assertJsonPath('data.0.payment.id', $payment->id)
            ->assertJsonPath('data.0.payment.status', 'confirmed')
            ->assertJsonPath('data.0.payment.receipt_number', 'REC000004');
    }

    public function test_event_without_payment_is_rejected(): void
    {
        [$admin, $token] = $this->loginWith(['event_participants.manage']);
        [$occurrence, $member] = $this->participation($admin->organization_id, ['requires_payment' => false, 'payment_amount' => null, 'payment_type' => null]);

        $this->withToken($token)
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants/{$member->id}/invoice")
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'event_not_payable');

        $this->withToken($token)
            ->get("/api/event-occurrences/{$occurrence->id}/participants/{$member->id}/invoice")
            ->assertNotFound();
    }

    public function test_cancelled_participant_is_rejected(): void
    {
        [$admin, $token] = $this->loginWith(['event_participants.manage']);
        [$occurrence, $member, $pivotId] = $this->participation($admin->organization_id);
        DB::table('event_occurrence_user')->where('id', $pivotId)->update(['status' => 'cancelled']);

        $this->withToken($token)
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants/{$member->id}/invoice")
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'participant_cancelled');
    }

    public function test_other_organization_participation_is_not_found(): void
    {
        [, $token] = $this->loginWith(['event_participants.manage', 'event_participants.view']);
        $otherOrganization = Organization::factory()->create();
        [$occurrence, $member, $pivotId] = $this->participation($otherOrganization->id);
        DB::table('event_occurrence_user')->where('id', $pivotId)->update(['invoice_number' => 'INV000001']);

        $this->withToken($token)
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants/{$member->id}/invoice")
            ->assertNotFound();
        $this->withToken($token)
            ->get("/api/event-occurrences/{$occurrence->id}/participants/{$member->id}/invoice")
            ->assertNotFound();
    }

    public function test_generation_requires_manage_right(): void
    {
        [$admin, $token] = $this->loginWith(['event_participants.view']);
        [$occurrence, $member] = $this->participation($admin->organization_id);

        $this->withToken($token)
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants/{$member->id}/invoice")
            ->assertForbidden();
    }

    /**
     * @return array{0: EventOccurrence, 1: User, 2: int}
     */
    private function participation(int $organizationId, array $eventOverrides = []): array
    {
        $event = Event::query()->create(array_merge([
            'organization_id' => $organizationId,
            'title' => 'Seminar',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'recurrence_type' => 'once',
            'start_date' => '2026-06-10',
            'requires_active_service' => false,
            'requires_payment' => true,
            'payment_amount' => 49.99,
            'payment_type' => 'EUR',
            'status' => 'active',
        ], $eventOverrides));
        $occurrence = EventOccurrence::query()->create([
            'organization_id' => $organizationId,
            'event_id' => $event->id,
            'occurrence_date' => '2026-06-10',
            'start_datetime' => '2026-06-10 10:00:00',
            'end_datetime' => '2026-06-10 11:00:00',
            'status' => 'scheduled',
        ]);
        $member = User::factory()->create(['organization_id' => $organizationId, 'first_name' => 'Ana', 'last_name' => 'Pop']);
        $pivotId = DB::table('event_occurrence_user')->insertGetId([
            'event_occurrence_id' => $occurrence->id,
            'user_id' => $member->id,
            'status' => 'registered',
            'registered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$occurrence, $member, $pivotId];
    }

    private function loginWith(array $rightNames): array
    {
        $user = User::factory()->create(['password' => 'password']);
        $group = Group::query()->create(['name' => fake()->unique()->slug(), 'label' => 'Events']);
        foreach ($rightNames as $rightName) {
            $right = Right::query()->firstOrCreate(['name' => $rightName], ['label' => $rightName]);
            $group->rights()->attach($right);
        }
        $user->groups()->attach($group);
        $token = $this->postJson('/api/login', ['email' => $user->email, 'organization_id' => $user->organization_id, 'password' => 'password'])->json('token');

        return [$user, $token];
    }
}
