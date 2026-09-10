<?php

namespace App\Articles\Models;

use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\Organization;
use App\Users\Models\User;
use App\Reporting\Models\Segment;
use App\Reporting\Services\SegmentService;
use App\Users\Models\Concerns\BelongsToAuthenticatedOrganization;
use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

#[Fillable(['title', 'description', 'publish_at', 'expires_at', 'priority', 'status', 'audience_segment', 'segment_id', 'created_by', 'organization_id'])]
class Article extends Model
{
    public const STATUSES = ['draft', 'scheduled', 'published', 'expired'];

    public const AUDIENCE_SEGMENTS = ['all_users', 'active_subscribers', 'expired_users', 'groups', 'locations'];

    use LogsModelChanges;
    use BelongsToAuthenticatedOrganization;
    use SetsOrganizationFromAuthenticatedUser;

    use HasFactory, SoftDeletes;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)->withTimestamps();
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ArticleReceipt::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function scopePublishable(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $segmentIds = app(SegmentService::class)->segmentIdsFor($user);

        return $query->publishable()
            // Deliberately do not include organization-less or other-organization announcements.
            ->where($this->qualifyColumn('organization_id'), $user->organization_id)
            ->where(function (Builder $query) use ($segmentIds, $user): void {
                $query->where(function (Builder $query): void {
                    $query->whereNull('segment_id')->where('audience_segment', 'all_users');
                })
                    ->orWhere(function (Builder $query) use ($segmentIds): void {
                        $query->whereNotNull('segment_id')->whereIn('segment_id', $segmentIds);
                    })
                    ->orWhere(function (Builder $query) use ($user): void {
                        $query->where('audience_segment', 'active_subscribers')
                            ->whereExists($this->serviceForUserQuery($user, active: true));
                    })->orWhere(function (Builder $query) use ($user): void {
                        $query->where('audience_segment', 'expired_users')
                            ->whereExists($this->serviceForUserQuery($user, active: false))
                            ->whereNotExists($this->serviceForUserQuery($user, active: true));
                    })->orWhere(function (Builder $query) use ($user): void {
                        $query->where('audience_segment', 'groups')->whereHas(
                            'groups', fn (Builder $groups) => $groups->whereIn('groups.id', $user->groups()->select('groups.id'))
                        );
                    })->orWhere(function (Builder $query) use ($user): void {
                        $query->where('audience_segment', 'locations')->whereHas(
                            'locations', fn (Builder $locations) => $locations->whereIn('locations.id', $user->locations()->select('locations.id'))
                        );
                    });
            });
    }

    private function serviceForUserQuery(User $user, bool $active): QueryBuilder
    {
        $query = DB::table('service_user')
            ->join('services', 'services.id', '=', 'service_user.service_id')
            ->where('service_user.user_id', $user->id)
            ->where('services.organization_id', $user->organization_id)
            ->where('services.is_active', true);

        if ($active) {
            return $query->where(fn ($query) => $query->whereNull('service_user.start_date')->orWhere('service_user.start_date', '<=', today()))
                ->where(fn ($query) => $query->whereNull('service_user.expires_at')->orWhere('service_user.expires_at', '>=', today()));
        }

        return $query->where('service_user.expires_at', '<', today());
    }

    protected function casts(): array
    {
        return ['publish_at' => 'datetime', 'expires_at' => 'datetime', 'priority' => 'integer'];
    }
}
