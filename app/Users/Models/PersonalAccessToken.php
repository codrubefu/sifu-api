<?php

namespace App\Users\Models;

use App\Users\Models\Organization;

use App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'token', 'abilities', 'last_used_at', 'expires_at'])]
class PersonalAccessToken extends Model
{
    use SetsOrganizationFromAuthenticatedUser;


    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    /** @use HasFactory<\Database\Factories\Factory<static>> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
