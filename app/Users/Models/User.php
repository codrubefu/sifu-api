<?php

namespace App\Users\Models;

use App\Users\Models\Organization;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Payments\Models\Payment;
use App\Articles\Models\Article;
use App\Service\Models\Service;
use App\Service\Models\ServiceUser;
use App\Service\Services\ServiceLifecycleService;
use App\Events\Models\EventOccurrence;
use App\Users\Models\Concerns\BelongsToAuthenticatedOrganization;
use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser;
use App\Users\Models\Scopes\LocationAccessScope;
use App\Users\Services\OrganizationAccessService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Notifications\Models\NotificationPreference;
use App\Notifications\Models\PushDevice;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['user_code', 'first_name', 'last_name', 'phone', 'active', 'email', 'password', 'organization_id', 'notification_consents', 'push_token', 'parent_user_id'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    use LogsModelChanges;
    use BelongsToAuthenticatedOrganization;
    use SetsOrganizationFromAuthenticatedUser;


    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(User::class, 'parent_user_id');
    }
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::addGlobalScope(new LocationAccessScope());
        static::updated(function (User $user): void {
            if ($user->wasChanged('password') || ($user->wasChanged('active') && ! $user->active)) {
                $user->accessTokens()->delete();
            }
        });
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(PersonalAccessToken::class);
    }

    public function passwordSetupTokens(): HasMany
    {
        return $this->hasMany(PasswordSetupToken::class);
    }

    public function consentRecords(): HasMany
    {
        return $this->hasMany(ConsentRecord::class);
    }

    public function registeredPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'admin_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(UserDocument::class);
    }

    public function userGrades(): HasMany
    {
        return $this->hasMany(UserGrade::class);
    }

    public function activeUserGrade(): HasOne
    {
        return $this->hasOne(UserGrade::class)->latestOfMany('obtained_at');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'created_by');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)->withTimestamps();
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)
            ->using(ServiceUser::class)
            ->withPivot(['id', 'invoice_number', 'bill_number', 'status', 'start_date', 'expires_at', 'accesses_used', 'activated_at', 'suspended_at', 'resume_at', 'status_reason', 'activation_payment_id'])
            ->withTimestamps();
    }

    public function activeServices(): BelongsToMany
    {
        $this->serviceAssignments()->with('service')->get()
            ->each(fn (ServiceUser $assignment) => app(ServiceLifecycleService::class)->refresh($assignment));

        return $this->services()
            ->where('services.is_active', true)
            ->wherePivot('status', 'active');
    }

    public function serviceAssignments(): HasMany
    {
        return $this->hasMany(ServiceUser::class);
    }

    public function eventOccurrences(): BelongsToMany
    {
        return $this->belongsToMany(EventOccurrence::class, 'event_occurrence_user')
            ->withPivot(['status', 'registered_at', 'notes'])
            ->withTimestamps();
    }

    public function hasRight(string $right): bool
    {
        if (app(OrganizationAccessService::class)->isRightDisabledForOrganization($right, $this->organization_id)) {
            return false;
        }

        if ($right === 'profile.view' && ! $this->hasExplicitRights()) {
            return true;
        }

        return $this->groups()
            ->whereHas('rights', fn ($query) => $query->where('name', $right))
            ->exists();
    }

    public function hasAnyRight(array $rights): bool
    {
        $rights = app(OrganizationAccessService::class)->availableRightNames($rights, $this->organization_id);

        if ($rights === []) {
            return false;
        }

        if (in_array('profile.view', $rights, true) && ! $this->hasExplicitRights()) {
            return true;
        }

        return $this->groups()
            ->whereHas('rights', fn ($query) => $query->whereIn('name', $rights))
            ->exists();
    }

    private function hasExplicitRights(): bool
    {
        return $this->groups()
            ->whereHas('rights')
            ->exists();
    }

    public function consentsTo(string $channel): bool
    {
        return $this->consentsToScope($channel, 'all');
    }

    public function consentsToScope(string $channel, string $scope): bool
    {
        $granted = $this->consentRecords()->where('purpose', 'notifications')->where('channel', $channel)
            ->latest('occurred_at')->latest('id')->value('granted');

        $optedOut = $this->notificationPreferences()->where('channel', $channel)
            ->whereIn('scope', ['all', $scope])->where('subscribed', false)->exists();

        $consented = $granted ?? ($this->notification_consents[$channel] ?? false);

        return ! $optedOut && (bool) $consented
            && match ($channel) {
                'sms' => filled($this->phone),
                'mail' => filled($this->email),
                'push' => $this->pushDevices()->exists() || filled($this->push_token),
                default => false,
            };
    }

    public function notificationPreferences(): HasMany { return $this->hasMany(NotificationPreference::class); }
    public function pushDevices(): HasMany { return $this->hasMany(PushDevice::class); }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_consents' => 'array',
        ];
    }
}
