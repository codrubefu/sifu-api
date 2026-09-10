<?php

namespace App\Notifications\Models;

use App\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushDevice extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['last_used_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
