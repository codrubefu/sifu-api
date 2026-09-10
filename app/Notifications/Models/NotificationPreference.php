<?php

namespace App\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['subscribed' => 'boolean']; }
}
