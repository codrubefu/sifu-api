<?php

namespace App\Dashboard\Services;

use App\Payments\Models\Payment;
use App\Service\Models\Service;
use App\Users\Models\AuditLog;
use App\Users\Models\Location;
use App\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function summary(int $organizationId, array $filters = []): array
    {
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();
        $groupBy = $filters['group_by'] ?? 'month';

        $confirmedPayments = Payment::query()
            ->where('organization_id', $organizationId)
            ->where('status', Payment::STATUS_CONFIRMED)
            ->whereBetween('paid_at', [$from, $to]);

        return [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'group_by' => $groupBy,
            ],
            'stats' => [
                'active_members' => User::query()->where('organization_id', $organizationId)->where('active', true)->count(),
                'flagged_services' => $this->flaggedServices($organizationId),
                'total_revenue' => (float) (clone $confirmedPayments)->sum('amount'),
                'active_locations' => Location::query()
                    ->where('organization_id', $organizationId)
                    ->whereHas('users')
                    ->count(),
            ],
            'revenue_by_period' => $this->revenueByPeriod($organizationId, $from, $to, $groupBy),
            'member_status' => $this->memberStatus($organizationId),
            'activity' => $this->activity($organizationId, $from, $to, $groupBy),
            'automations' => $this->automations($organizationId),
        ];
    }

    private function flaggedServices(int $organizationId): int
    {
        return DB::table('service_user as su')
            ->join('services as s', 's.id', '=', 'su.service_id')
            ->where('s.organization_id', $organizationId)
            ->where(function ($query): void {
                $query->whereIn('su.status', ['expired', 'suspended'])
                    ->orWhereBetween('su.expires_at', [now(), now()->addDays(7)]);
            })
            ->count();
    }

    private function revenueByPeriod(int $organizationId, Carbon $from, Carbon $to, string $groupBy): array
    {
        $periodExpression = $this->periodExpression($groupBy, 'payments.paid_at');

        return Payment::query()
            ->where('organization_id', $organizationId)
            ->where('status', Payment::STATUS_CONFIRMED)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw($periodExpression.' as period')
            ->selectRaw('SUM(amount) as revenue')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row): array => ['period' => $row->period, 'revenue' => (float) $row->revenue])
            ->all();
    }

    private function memberStatus(int $organizationId): array
    {
        $active = User::query()->where('organization_id', $organizationId)->where('active', true)->count();
        $inactive = User::query()->where('organization_id', $organizationId)->where('active', false)->count();
        $expired = DB::table('service_user as su')
            ->join('services as s', 's.id', '=', 'su.service_id')
            ->where('s.organization_id', $organizationId)
            ->where('su.status', 'expired')
            ->distinct('su.user_id')
            ->count('su.user_id');
        $suspended = DB::table('service_user as su')
            ->join('services as s', 's.id', '=', 'su.service_id')
            ->where('s.organization_id', $organizationId)
            ->where('su.status', 'suspended')
            ->distinct('su.user_id')
            ->count('su.user_id');

        return [
            ['status' => 'active', 'count' => $active],
            ['status' => 'inactive', 'count' => $inactive],
            ['status' => 'expired', 'count' => $expired],
            ['status' => 'suspended', 'count' => $suspended],
        ];
    }

    private function activity(int $organizationId, Carbon $from, Carbon $to, string $groupBy): array
    {
        $periodExpression = $this->periodExpression($groupBy, 'created_at');

        return AuditLog::query()
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw($periodExpression.' as period')
            ->selectRaw('COUNT(*) as active')
            ->selectRaw("SUM(CASE WHEN COALESCE(event_type, action) = 'sms.sent' THEN 1 ELSE 0 END) as messages")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row): array => [
                'period' => $row->period,
                'active' => (int) $row->active,
                'messages' => (int) $row->messages,
            ])
            ->all();
    }

    private function automations(int $organizationId): array
    {
        return [
            [
                'key' => 'service_expiry_notifications',
                'label' => 'Service expiry notifications',
                'enabled' => true,
                'helper' => 'Scheduled daily notification workflow.',
                'count' => $this->flaggedServices($organizationId),
            ],
            [
                'key' => 'payment_activation',
                'label' => 'Payment activation',
                'enabled' => true,
                'helper' => 'Confirmed payments can activate service assignments.',
                'count' => Payment::query()->where('organization_id', $organizationId)->where('status', Payment::STATUS_CONFIRMED)->count(),
            ],
            [
                'key' => 'service_expiry',
                'label' => 'Service expiry',
                'enabled' => true,
                'helper' => 'Service lifecycle refresh marks expired and consumed assignments.',
                'count' => DB::table('service_user as su')->join('services as s', 's.id', '=', 'su.service_id')->where('s.organization_id', $organizationId)->count(),
            ],
            [
                'key' => 'scheduled_announcements',
                'label' => 'Scheduled announcements',
                'enabled' => true,
                'helper' => 'Article publication status is transitioned by schedule.',
                'count' => DB::table('articles')->where('organization_id', $organizationId)->count(),
            ],
            [
                'key' => 'services_total',
                'label' => 'Total defined services',
                'enabled' => true,
                'helper' => 'Active and inactive service definitions.',
                'count' => Service::query()->where('organization_id', $organizationId)->count(),
            ],
        ];
    }

    private function periodExpression(string $period, string $column): string
    {
        if ($period === 'day') {
            return "date({$column})";
        }

        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '%Y-%m')",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            'sqlsrv' => "CONVERT(varchar(7), {$column}, 120)",
            default => "strftime('%Y-%m', {$column})",
        };
    }
}
