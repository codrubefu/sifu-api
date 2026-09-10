<?php

namespace App\Notifications\Models;

use App\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Campaigns\Models\Campaign;

class NotificationDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['payload' => 'array', 'sent_at' => 'datetime']; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function attempts(): HasMany { return $this->hasMany(NotificationAttempt::class); }
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
}
