<?php

namespace App\Reporting\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ServiceExpirationReportService
{
    public const CATEGORIES = ['expiring_soon', 'expired', 'suspended', 'not_renewed'];

    public function report(int $organizationId, array $filters): array
    {
        $rows = $this->rows($organizationId, $filters);

        return collect(self::CATEGORIES)->mapWithKeys(fn (string $category) => [
            $category => $rows->where('category', $category)->values()->all(),
        ])->all();
    }

    public function rows(int $organizationId, array $filters): Collection
    {
        $today = CarbonImmutable::today();
        $query = $this->query($organizationId, $filters);

        return $query->orderBy('su.expires_at')->orderBy('su.id')->get()->map(function (object $row) use ($today): array {
            $expiresAt = $row->expires_at ? CarbonImmutable::parse($row->expires_at) : null;

            return [
                'assignment_id' => $row->assignment_id,
                'user_id' => $row->user_id,
                'member_name' => trim($row->first_name.' '.$row->last_name),
                'phone' => $row->phone,
                'service_id' => $row->service_id,
                'service_name' => $row->service_name,
                'service_type' => $row->service_type,
                'status' => $row->status,
                'start_date' => $row->start_date,
                'expires_at' => $row->expires_at,
                'days_until_expiration' => $expiresAt ? (int) $today->diffInDays($expiresAt->startOfDay(), false) : null,
                'last_notification_at' => $row->last_notification_at,
                'category' => $this->category($row, $today),
            ];
        })->when(isset($filters['category']), fn (Collection $rows) => $rows
            ->where('category', $filters['category'])->values());
    }

    private function query(int $organizationId, array $filters): Builder
    {
        $query = DB::table('service_user as su')
            ->join('services as s', 's.id', '=', 'su.service_id')
            ->join('users as u', 'u.id', '=', 'su.user_id')
            ->where('s.organization_id', $organizationId)
            ->select([
                'su.id as assignment_id', 'su.user_id', 'u.first_name', 'u.last_name', 'u.phone',
                'su.service_id', 's.name as service_name', 's.type as service_type', 'su.status',
                'su.start_date', 'su.expires_at',
            ])->selectSub(
                DB::table('sms_messages as sms')->selectRaw('MAX(sms.sent_at)')
                    ->whereColumn('sms.service_user_id', 'su.id'),
                'last_notification_at',
            )->selectSub(
                DB::table('service_user as renewal')->selectRaw('COUNT(*)')
                    ->whereColumn('renewal.user_id', 'su.user_id')
                    ->whereColumn('renewal.service_id', 'su.service_id')
                    ->whereColumn('renewal.id', '>', 'su.id'),
                'renewal_count',
            );

        if (isset($filters['location_id'])) {
            $query->whereExists(fn (Builder $location) => $location->selectRaw('1')
                ->from('location_user as lu')->whereColumn('lu.user_id', 'u.id')
                ->where('lu.location_id', $filters['location_id']));
        }
        if (isset($filters['service_type'])) {
            $query->where('s.type', $filters['service_type']);
        }
        if (isset($filters['status'])) {
            $query->where('su.status', $filters['status']);
        }
        if (isset($filters['expires_in_days_from'])) {
            $query->whereDate('su.expires_at', '>=', now()->addDays($filters['expires_in_days_from'])->toDateString());
        }
        if (isset($filters['expires_in_days_to'])) {
            $query->whereDate('su.expires_at', '<=', now()->addDays($filters['expires_in_days_to'])->toDateString());
        }

        return $query;
    }

    private function category(object $row, CarbonImmutable $today): string
    {
        if ($row->status === 'suspended') {
            return 'suspended';
        }
        if ($row->expires_at && CarbonImmutable::parse($row->expires_at)->startOfDay()->lt($today)) {
            return (int) $row->renewal_count > 0 ? 'expired' : 'not_renewed';
        }

        return 'expiring_soon';
    }
}
