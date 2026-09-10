<?php

namespace App\Reporting\Services;

use App\Payments\Models\Payment;
use App\Reporting\Models\Segment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    private const AGING_BUCKETS = [
        7 => '1-7',
        30 => '8-30',
        60 => '31-60',
    ];

    public function __construct(private readonly SegmentService $segments) {}

    public function aggregate(int $organizationId, array $filters): array
    {
        $payments = $this->payments($organizationId, $filters);
        $confirmed = (clone $payments)->where('payments.status', Payment::STATUS_CONFIRMED);
        $confirmedTotal = (float) (clone $confirmed)->sum('payments.amount');
        $refundedTotal = (float) (clone $payments)->where('payments.status', Payment::STATUS_REFUNDED)->sum('payments.amount');

        $assignments = DB::table('service_user as su')
            ->join('services as s', 's.id', '=', 'su.service_id')
            ->join('users as member', 'member.id', '=', 'su.user_id')
            ->where('s.organization_id', $organizationId);
        $memberIds = $this->segmentMemberIds($organizationId, $filters);
        if ($memberIds !== null) {
            $assignments->whereIn('member.id', $memberIds);
        }
        if (isset($filters['service_type'])) {
            $assignments->where('s.type', $filters['service_type']);
        }
        if (isset($filters['service_id'])) {
            $assignments->where('s.id', $filters['service_id']);
        }
        if (isset($filters['location_id'])) {
            $assignments->whereExists(fn ($q) => $q->selectRaw('1')->from('location_user as lu')
                ->whereColumn('lu.user_id', 'member.id')->where('lu.location_id', $filters['location_id']));
        }
        $invoiced = (float) (clone $assignments)->sum('s.price');
        $serviceRevenue = (float) (clone $confirmed)
            ->where('payments.model_type', Payment::MODEL_TYPE_SERVICE_USER)->sum('payments.amount');

        $renewals = (clone $assignments)->whereExists(fn ($q) => $q->selectRaw('1')
            ->from('service_user as previous')
            ->whereColumn('previous.user_id', 'su.user_id')
            ->whereColumn('previous.service_id', 'su.service_id')
            ->whereColumn('previous.id', '<', 'su.id'))->count();

        $groupBy = $filters['group_by'] ?? 'month';
        $revenue = [];
        $serviceBreakdown = [];
        if (in_array($groupBy, ['service', 'service_type'], true)) {
            $serviceBreakdown = $this->serviceBreakdown($organizationId, $filters, $groupBy);
        } else {
            $dateExpression = $this->periodExpression($groupBy);
            $revenue = (clone $confirmed)->selectRaw($dateExpression.' as period')
                ->selectRaw('SUM(payments.amount) as total')->groupBy('period')->orderBy('period')->get()
                ->map(fn ($row) => ['period' => $row->period, 'total' => (float) $row->total])->all();
        }

        $bank = (clone $confirmed)->where('payments.payment_type_id', Payment::TYPE_BANK_TRANSFER);

        return [
            'totals' => ['confirmed' => $confirmedTotal, 'refunded' => $refundedTotal, 'net' => $confirmedTotal - $refundedTotal, 'count' => (clone $payments)->count()],
            'revenue_by_period' => $revenue,
            $groupBy === 'service_type' ? 'revenue_by_service_type' : 'revenue_by_service' => $serviceBreakdown,
            'receivables' => ['invoiced' => $invoiced, 'paid' => $serviceRevenue, 'outstanding' => max(0, $invoiced - $serviceRevenue)],
            'renewals' => $renewals,
            'bank_reconciliation' => [
                'total' => (float) (clone $bank)->sum('payments.amount'),
                'reconciled' => (float) (clone $bank)->whereNotNull('payments.reconciled_at')->sum('payments.amount'),
                'unreconciled' => (float) (clone $bank)->whereNull('payments.reconciled_at')->sum('payments.amount'),
            ],
        ];
    }

    public function rows(int $organizationId, array $filters): array
    {
        return $this->payments($organizationId, $filters)->orderBy('paid_at')->get([
            'id', 'paid_at', 'first_name', 'last_name', 'amount', 'status', 'payment_type_id',
            'model_type', 'model_id', 'admin_id', 'location_id', 'bank_reference', 'reconciled_at',
        ])->map(fn (Payment $payment) => [
            $payment->id, $payment->paid_at?->toIso8601String(), $payment->first_name, $payment->last_name,
            $payment->amount, $payment->status, $payment->paymentTypeName(), $payment->model_type,
            $payment->model_id, $payment->admin_id, $payment->location_id, $payment->bank_reference,
            $payment->reconciled_at?->toIso8601String(),
        ])->all();
    }

    /**
     * Return one row for every service assignment (the payment obligation).
     *
     * Confirmed payments are deliberately aggregated before they are joined so
     * an obligation is not duplicated when it has been paid in instalments.
     */
    public function receivableRows(int $organizationId, array $filters): array
    {
        $confirmedPayments = DB::table('payments')
            ->select('model_id')
            ->selectRaw('SUM(amount) as paid_amount')
            ->where('organization_id', $organizationId)
            ->where('model_type', Payment::MODEL_TYPE_SERVICE_USER)
            ->where('status', Payment::STATUS_CONFIRMED)
            ->groupBy('model_id');

        $query = DB::table('service_user as su')
            ->join('services as s', 's.id', '=', 'su.service_id')
            ->join('users as member', 'member.id', '=', 'su.user_id')
            ->leftJoinSub($confirmedPayments, 'confirmed_payments', fn ($join) => $join->on('confirmed_payments.model_id', '=', 'su.id'))
            ->where('s.organization_id', $organizationId)
            ->select([
                'su.id as obligation_id',
                'su.created_at as invoiced_at',
                'su.invoice_number',
                's.id as service_id',
                's.name as service_name',
                's.currency',
                'member.id as member_id',
                'member.first_name',
                'member.last_name',
                's.price as invoiced_amount',
            ])
            ->selectRaw('COALESCE(confirmed_payments.paid_amount, 0) as paid_amount')
            ->orderBy('su.created_at')
            ->orderBy('su.id');

        if (isset($filters['location_id'])) {
            $query->whereExists(fn ($location) => $location->selectRaw('1')
                ->from('location_user as lu')
                ->whereColumn('lu.user_id', 'member.id')
                ->where('lu.location_id', $filters['location_id']));
        }
        if (isset($filters['service_id'])) {
            $query->where('s.id', $filters['service_id']);
        }
        if (isset($filters['service_type'])) {
            $query->where('s.type', $filters['service_type']);
        }
        if (isset($filters['member_id'])) {
            $query->where('member.id', $filters['member_id']);
        }
        if (isset($filters['from'])) {
            $query->where('su.created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (isset($filters['to'])) {
            $query->where('su.created_at', '<=', $filters['to'].' 23:59:59');
        }

        $memberIds = $this->segmentMemberIds($organizationId, $filters);
        if ($memberIds !== null) {
            $query->whereIn('member.id', $memberIds);
        }

        return $query->get()->map(function ($row): array {
            $invoiced = (float) $row->invoiced_amount;
            $paid = (float) $row->paid_amount;
            $balance = $invoiced - $paid;
            $daysOverdue = $balance > 0
                ? (int) max(0, Carbon::parse($row->invoiced_at)->startOfDay()->diffInDays(today(), false))
                : 0;

            return [
                'obligation_id' => $row->obligation_id,
                'invoiced_at' => Carbon::parse($row->invoiced_at)->toIso8601String(),
                'invoice_number' => $row->invoice_number,
                'service_id' => $row->service_id,
                'service_name' => $row->service_name,
                'member_id' => $row->member_id,
                'member_name' => trim($row->first_name.' '.$row->last_name),
                'currency' => $row->currency,
                'invoiced_amount' => $invoiced,
                'paid_amount' => $paid,
                'balance' => $balance,
                'days_overdue' => $daysOverdue,
                'aging_bucket' => $this->agingBucket($daysOverdue, $balance),
            ];
        })->all();
    }

    private function agingBucket(int $daysOverdue, float $balance): ?string
    {
        if ($balance <= 0 || $daysOverdue === 0) {
            return null;
        }

        foreach (self::AGING_BUCKETS as $maximum => $bucket) {
            if ($daysOverdue <= $maximum) {
                return $bucket;
            }
        }

        return '60+';
    }

    private function payments(int $organizationId, array $filters): Builder
    {
        $query = Payment::query()->where('payments.organization_id', $organizationId);
        foreach (['location_id', 'admin_id', 'payment_type_id', 'status'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where("payments.{$filter}", $filters[$filter]);
            }
        }
        if (isset($filters['from'])) $query->where('payments.paid_at', '>=', $filters['from'].' 00:00:00');
        if (isset($filters['to'])) $query->where('payments.paid_at', '<=', $filters['to'].' 23:59:59');
        if (isset($filters['service_type'])) {
            $query->where('payments.model_type', Payment::MODEL_TYPE_SERVICE_USER)
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('service_user as su')
                    ->join('services as s', 's.id', '=', 'su.service_id')
                    ->whereColumn('su.id', 'payments.model_id')->where('s.type', $filters['service_type'])
                    ->where('s.organization_id', $organizationId));
        }
        if (isset($filters['service_id'])) {
            $query->where('payments.model_type', Payment::MODEL_TYPE_SERVICE_USER)
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('service_user as filtered_su')
                    ->join('services as filtered_s', 'filtered_s.id', '=', 'filtered_su.service_id')
                    ->whereColumn('filtered_su.id', 'payments.model_id')
                    ->where('filtered_s.id', $filters['service_id'])
                    ->where('filtered_s.organization_id', $organizationId));
        }
        $memberIds = $this->segmentMemberIds($organizationId, $filters);
        if ($memberIds !== null) {
            $query->where('payments.model_type', Payment::MODEL_TYPE_SERVICE_USER)
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('service_user as segment_su')
                    ->whereColumn('segment_su.id', 'payments.model_id')->whereIn('segment_su.user_id', $memberIds));
        }
        return $query;
    }

    private function serviceBreakdown(int $organizationId, array $filters, string $groupBy): array
    {
        $paymentTotals = $this->payments($organizationId, $filters)
            ->where('payments.model_type', Payment::MODEL_TYPE_SERVICE_USER)
            ->selectRaw('payments.model_id as assignment_id')
            ->selectRaw('SUM(CASE WHEN payments.status = ? THEN payments.amount ELSE 0 END) as confirmed', [Payment::STATUS_CONFIRMED])
            ->selectRaw('SUM(CASE WHEN payments.status = ? THEN payments.amount ELSE 0 END) as refunded', [Payment::STATUS_REFUNDED])
            ->groupBy('payments.model_id');

        $query = DB::table('service_user as su')
            ->join('services as s', 's.id', '=', 'su.service_id')
            ->join('users as member', 'member.id', '=', 'su.user_id')
            ->leftJoinSub($paymentTotals, 'payment_totals', fn ($join) => $join->on('payment_totals.assignment_id', '=', 'su.id'))
            ->where('s.organization_id', $organizationId);

        if (isset($filters['service_id'])) {
            $query->where('s.id', $filters['service_id']);
        }
        if (isset($filters['service_type'])) {
            $query->where('s.type', $filters['service_type']);
        }
        if (($memberIds = $this->segmentMemberIds($organizationId, $filters)) !== null) {
            $query->whereIn('member.id', $memberIds);
        }
        if (isset($filters['location_id'])) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('location_user as lu')
                ->whereColumn('lu.user_id', 'member.id')->where('lu.location_id', $filters['location_id']));
        }

        $columns = $groupBy === 'service'
            ? ['s.id', 's.name', 's.type']
            : ['s.type'];

        return $query->select($columns)
            ->selectRaw('COUNT(su.id) as subscriptions')
            ->selectRaw('COUNT(DISTINCT su.user_id) as members')
            ->selectRaw('SUM(s.price) as invoiced')
            ->selectRaw('SUM(COALESCE(payment_totals.confirmed, 0)) as confirmed')
            ->selectRaw('SUM(COALESCE(payment_totals.refunded, 0)) as refunded')
            ->groupBy(...$columns)
            ->orderBy($groupBy === 'service' ? 's.name' : 's.type')
            ->get()
            ->map(function ($row) use ($groupBy): array {
                $invoiced = (float) $row->invoiced;
                $confirmed = (float) $row->confirmed;
                $refunded = (float) $row->refunded;
                $members = (int) $row->members;

                return array_filter([
                    'service_id' => $groupBy === 'service' ? (int) $row->id : null,
                    'service_name' => $groupBy === 'service' ? $row->name : null,
                    'service_type' => $row->type,
                    'subscriptions' => (int) $row->subscriptions,
                    'invoiced' => $invoiced,
                    'confirmed' => $confirmed,
                    'refunded' => $refunded,
                    'outstanding' => max(0, $invoiced - $confirmed + $refunded),
                    'average_revenue_per_member' => $members > 0 ? ($confirmed - $refunded) / $members : 0.0,
                ], fn ($value) => $value !== null);
            })->all();
    }

    private function periodExpression(string $period): string
    {
        if ($period === 'day') {
            return 'date(payments.paid_at)';
        }

        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "DATE_FORMAT(payments.paid_at, '%Y-%m')",
            'pgsql' => "to_char(payments.paid_at, 'YYYY-MM')",
            'sqlsrv' => "CONVERT(varchar(7), payments.paid_at, 120)",
            default => "strftime('%Y-%m', payments.paid_at)",
        };
    }

    private function segmentMemberIds(int $organizationId, array $filters): ?array
    {
        if (! isset($filters['segment_id'])) {
            return null;
        }
        $segment = Segment::query()->where('organization_id', $organizationId)->findOrFail($filters['segment_id']);
        return $this->segments->members($segment)->pluck('users.id')->all();
    }
}
