<?php

namespace App\Users\Models;

use App\Users\Models\Concerns\BelongsToAuthenticatedOrganization;
use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['organization_id', 'name', 'description', 'is_active'])]
class Grade extends Model
{
    use BelongsToAuthenticatedOrganization;
    use LogsModelChanges;
    use SetsOrganizationFromAuthenticatedUser;
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function userGrades(): HasMany
    {
        return $this->hasMany(UserGrade::class);
    }

    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, UserGrade::class, 'grade_id', 'id', 'id', 'user_id');
    }
}
