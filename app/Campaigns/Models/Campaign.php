<?php

namespace App\Campaigns\Models;

use App\Notifications\Models\NotificationDelivery;
use App\Reporting\Models\Segment;
use App\Users\Models\Concerns\BelongsToAuthenticatedOrganization;
use App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser;
use App\Users\Models\Organization;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'segment_id', 'created_by', 'name', 'channel', 'subject', 'content', 'status', 'scheduled_at', 'cancelled_at', 'dispatched_at'])]
class Campaign extends Model
{
    use BelongsToAuthenticatedOrganization, SetsOrganizationFromAuthenticatedUser;

    public const CHANNELS = ['mail', 'sms'];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function segment(): BelongsTo { return $this->belongsTo(Segment::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function deliveries(): HasMany { return $this->hasMany(NotificationDelivery::class); }

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'cancelled_at' => 'datetime', 'dispatched_at' => 'datetime'];
    }
}
