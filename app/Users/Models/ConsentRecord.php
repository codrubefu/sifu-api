<?php

namespace App\Users\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'user_id', 'purpose', 'channel', 'policy_version', 'granted', 'occurred_at', 'source', 'actor_id'])]
class ConsentRecord extends Model
{
    protected function casts(): array
    {
        return ['granted' => 'boolean', 'occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        // A consent is evidence: corrections are represented by a new row.
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }
}
