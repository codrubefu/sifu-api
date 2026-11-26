<?php

namespace App\Events\Models;

use App\Service\Models\Service;
use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\Organization;
use App\Users\Models\User;
use App\Users\Models\Concerns\BelongsToAuthenticatedOrganization;
use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable([
    'category_id','location_id','instructor_id','group_id','title','description','location','start_time','end_time','recurrence_type','recurrence_days','monthly_day','start_date','end_date','occurrences_generated_until','requires_active_service','requires_payment','payment_amount','payment_type','max_participants','status','organization_id',
])]
class Event extends Model
{
    use LogsModelChanges;
    use BelongsToAuthenticatedOrganization;
    use SetsOrganizationFromAuthenticatedUser;

    use HasFactory, SoftDeletes;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(EventOccurrence::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    public function serviceRequirements(): HasMany
    {
        return $this->hasMany(EventServiceRequirement::class)->orderBy('group_index')->orderBy('id');
    }

    /**
     * Required service groups ordered by `group_index` (re-indexed from 0).
     * Inside a group any service is enough (OR); every group must be satisfied (AND).
     * Services that are no longer visible (soft deleted / other tenant) are skipped, and a
     * group left without any visible service disappears (it could never be satisfied).
     *
     * @return Collection<int, Collection<int, Service>>
     */
    public function serviceGroups(): Collection
    {
        return $this->serviceRequirements
            ->filter(fn (EventServiceRequirement $requirement): bool => $requirement->service !== null)
            ->groupBy('group_index')
            ->sortKeys()
            ->map(fn (Collection $requirements): Collection => $requirements->map(fn (EventServiceRequirement $requirement): Service => $requirement->service)->values())
            ->values();
    }

    /**
     * Same shape as serviceGroups(), as plain service IDs. The group indices match the
     * `service_groups` API field and the `missing_service_groups` refusal detail.
     *
     * @return array<int, array<int, int>>
     */
    public function serviceGroupIds(): array
    {
        return $this->serviceGroups()
            ->map(fn (Collection $services): array => $services->map(fn (Service $service): int => (int) $service->id)->all())
            ->all();
    }

    /**
     * Replaces the event's service groups. Expects already-validated, non-empty groups.
     *
     * @param array<int, array<int, int>> $groups
     */
    public function syncServiceGroups(array $groups): void
    {
        EventServiceRequirement::query()->where('event_id', $this->id)->delete();

        $now = now();
        $rows = [];
        foreach (array_values($groups) as $index => $serviceIds) {
            foreach (array_values(array_unique(array_map('intval', $serviceIds))) as $serviceId) {
                $rows[] = ['event_id' => $this->id, 'group_index' => $index, 'service_id' => $serviceId, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        if ($rows !== []) {
            EventServiceRequirement::query()->insert($rows);
        }

        $this->unsetRelation('serviceRequirements');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    protected function casts(): array
    {
        return ['recurrence_days' => 'array','monthly_day' => 'integer','start_date' => 'date:Y-m-d','end_date' => 'date:Y-m-d','occurrences_generated_until' => 'date:Y-m-d','requires_active_service' => 'boolean','requires_payment' => 'boolean','payment_amount' => 'decimal:2','max_participants' => 'integer'];
    }
}
