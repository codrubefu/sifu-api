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

    /**
     * MySQL/InnoDB cannot index the full 2048-char raw token (max key length 3072 bytes
     * with utf8mb4), so uniqueness/lookups go through a SHA-256 hash kept in sync here,
     * mirroring the personal_access_tokens precedent.
     */
    protected function setTokenAttribute(?string $value): void
    {
        $this->attributes['token'] = $value;
        $this->attributes['token_hash'] = $value === null ? null : hash('sha256', $value);
    }
}
