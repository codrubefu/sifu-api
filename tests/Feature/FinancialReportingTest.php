<?php

namespace Tests\Feature;

use App\Payments\Models\Payment;
use App\Service\Models\Service;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_periods_and_bank_reconciliation_are_aggregated(): void
    {
        [$operator, $token] = $this->loginWith('reports.view');
        $this->payment($operator, 100, Payment::STATUS_CONFIRMED, Payment::TYPE_BANK_TRANSFER, '2026-07-01', true);
        $this->payment($operator, 40, Payment::STATUS_CONFIRMED, Payment::TYPE_BANK_TRANSFER, '2026-07-15');
        $this->payment($operator, 10, Payment::STATUS_REFUNDED, Payment::TYPE_CARD, '2026-07-20');

        $this->withToken($token)->getJson('/api/reports/financial?from=2026-07-01&to=2026-07-31')
            ->assertOk()->assertJsonPath('data.totals.confirmed', 140)
            ->assertJsonPath('data.totals.net', 130)
            ->assertJsonPath('data.revenue_by_period.0.period', '2026-07')
            ->assertJsonPath('data.bank_reconciliation.reconciled', 100)
            ->assertJsonPath('data.bank_reconciliation.unreconciled', 40);
    }

    public function test_report_cannot_read_or_request_another_organization(): void
    {
        [$operator, $token] = $this->loginWith('reports.view');
        $outsider = User::factory()->create();
        $this->payment($outsider, 999, Payment::STATUS_CONFIRMED, Payment::TYPE_CARD, '2026-07-01');

        $this->withToken($token)->getJson('/api/reports/financial')
            ->assertOk()->assertJsonPath('data.totals.confirmed', 0);
        $this->withToken($token)->getJson('/api/reports/financial?organization_id='.$outsider->organization_id)
            ->assertForbidden();
    }

    public function test_report_can_group_subscription_finances_by_service_and_service_type(): void
    {
        [$operator, $token] = $this->loginWith('reports.view');
        $member = User::factory()->create(['organization_id' => $operator->organization_id]);
        $service = Service::query()->create([
            'organization_id' => $operator->organization_id,
            'name' => 'Annual membership',
            'description' => 'Membership',
            'type' => 'membership',
            'price' => 120,
            'currency' => 'RON',
            'max_users' => 20,
            'is_active' => true,
        ]);
        $service->users()->attach($member);
        $assignmentId = $service->users()->whereKey($member->id)->first()->pivot->id;

        foreach ([[100, Payment::STATUS_CONFIRMED], [20, Payment::STATUS_REFUNDED]] as [$amount, $status]) {
            Payment::query()->create([
                'organization_id' => $operator->organization_id,
                'first_name' => 'Test',
                'last_name' => 'Member',
                'payment_type_id' => Payment::TYPE_CARD,
                'status' => $status,
                'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
                'model_id' => $assignmentId,
                'amount' => $amount,
                'paid_at' => '2026-08-10',
                'admin_id' => $operator->id,
            ]);
        }

        $this->withToken($token)->getJson('/api/reports/financial?group_by=service&service_id='.$service->id)
            ->assertOk()
            ->assertJsonPath('data.revenue_by_service.0.service_name', 'Annual membership')
            ->assertJsonPath('data.revenue_by_service.0.subscriptions', 1)
            ->assertJsonPath('data.revenue_by_service.0.invoiced', 120)
            ->assertJsonPath('data.revenue_by_service.0.confirmed', 100)
            ->assertJsonPath('data.revenue_by_service.0.refunded', 20)
            ->assertJsonPath('data.revenue_by_service.0.outstanding', 40)
            ->assertJsonPath('data.revenue_by_service.0.average_revenue_per_member', 80);

        $this->withToken($token)->getJson('/api/reports/financial?group_by=service_type')
            ->assertOk()
            ->assertJsonPath('data.revenue_by_service_type.0.service_type', 'membership')
            ->assertJsonMissingPath('data.revenue_by_service_type.0.service_id');
    }

    public function test_financial_documents_report_lists_and_downloads_documents_for_period(): void
    {
        [$operator, $token] = $this->loginWith('reports.manage');
        $member = User::factory()->create(['organization_id' => $operator->organization_id]);
        $service = Service::query()->create([
            'organization_id' => $operator->organization_id,
            'name' => 'Membership',
            'description' => 'Membership',
            'price' => 120,
            'currency' => 'RON',
            'duration_days' => null,
            'max_users' => 20,
            'is_active' => true,
        ]);
        $service->users()->attach($member, [
            'invoice_number' => 'INV000001',
            'bill_number' => 'BILL000001',
            'status' => 'active',
            'start_date' => '2026-08-01',
        ]);
        $assignmentId = $service->users()->whereKey($member->id)->first()->pivot->id;
        DB::table('service_user')->where('id', $assignmentId)->update(['created_at' => '2026-08-10 09:00:00']);

        $payment = Payment::query()->create([
            'organization_id' => $operator->organization_id,
            'first_name' => 'Test',
            'last_name' => 'Member',
            'payment_type_id' => Payment::TYPE_CASH,
            'status' => Payment::STATUS_CONFIRMED,
            'receipt_number' => 'REC000001',
            'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
            'model_id' => $assignmentId,
            'amount' => 120,
            'paid_at' => '2026-08-11 10:00:00',
            'admin_id' => $operator->id,
        ]);

        $this->withToken($token)
            ->getJson('/api/reports/financial-documents?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['type' => 'invoice', 'number' => 'INV000001'])
            ->assertJsonFragment(['type' => 'payment_note', 'number' => 'BILL000001'])
            ->assertJsonFragment(['type' => 'receipt', 'number' => 'REC000001']);

        $this->withToken($token)
            ->get("/api/reports/financial-documents/invoice/{$assignmentId}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->withToken($token)
            ->get("/api/reports/financial-documents/invoice/{$assignmentId}/download/xml")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');

        $this->withToken($token)
            ->get('/api/reports/financial-documents/download?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip');
    }

    public function test_receivables_return_one_aged_row_per_obligation_and_support_filters(): void
    {
        Carbon::setTestNow('2026-08-29 12:00:00');
        [$operator, $token] = $this->loginWith('reports.view');
        $member = User::factory()->create([
            'organization_id' => $operator->organization_id,
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
        ]);
        $otherMember = User::factory()->create(['organization_id' => $operator->organization_id]);
        $service = Service::query()->create([
            'organization_id' => $operator->organization_id,
            'name' => 'Curs',
            'description' => 'Curs',
            'price' => 100,
            'currency' => 'RON',
            'duration_days' => null,
            'max_users' => 20,
            'is_active' => true,
        ]);
        $service->users()->attach([$member->id, $otherMember->id]);
        $assignmentId = (int) DB::table('service_user')->where('service_id', $service->id)
            ->where('user_id', $member->id)->value('id');
        DB::table('service_user')->where('id', $assignmentId)->update(['created_at' => '2026-07-20 09:00:00']);

        foreach ([25, 15] as $amount) {
            Payment::query()->create([
                'organization_id' => $operator->organization_id,
                'first_name' => 'Ana',
                'last_name' => 'Popescu',
                'payment_type_id' => Payment::TYPE_CASH,
                'status' => Payment::STATUS_CONFIRMED,
                'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
                'model_id' => $assignmentId,
                'amount' => $amount,
                'paid_at' => '2026-08-01',
                'admin_id' => $operator->id,
            ]);
        }

        $this->withToken($token)
            ->getJson("/api/reports/financial/receivables?service_id={$service->id}&member_id={$member->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.obligation_id', $assignmentId)
            ->assertJsonPath('data.0.member_name', 'Ana Popescu')
            ->assertJsonPath('data.0.invoiced_amount', 100)
            ->assertJsonPath('data.0.paid_amount', 40)
            ->assertJsonPath('data.0.balance', 60)
            ->assertJsonPath('data.0.days_overdue', 40)
            ->assertJsonPath('data.0.aging_bucket', '31-60');

        Carbon::setTestNow();
    }

    private function payment(User $operator, float $amount, string $status, int $type, string $date, bool $reconciled = false): void
    {
        Payment::query()->create([
            'organization_id' => $operator->organization_id, 'first_name' => 'Test', 'last_name' => 'Member',
            'payment_type_id' => $type, 'status' => $status, 'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
            'amount' => $amount, 'paid_at' => $date, 'admin_id' => $operator->id,
            'reconciled_at' => $reconciled ? $date : null,
        ]);
    }

    private function loginWith(string $rightName): array
    {
        $user = User::factory()->create(['password' => 'password']);
        $right = Right::query()->create(['name' => $rightName, 'label' => $rightName]);
        $group = Group::query()->create(['name' => fake()->unique()->slug(), 'label' => 'Reports']);
        $group->rights()->attach($right);
        $user->groups()->attach($group);
        $token = $this->postJson('/api/login', ['email' => $user->email, 'organization_id' => $user->organization_id, 'password' => 'password'])->json('token');
        return [$user, $token];
    }
}
