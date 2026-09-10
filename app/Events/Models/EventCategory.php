<?php

namespace App\Events\Models;

use App\Users\Models\Concerns\BelongsToAuthenticatedOrganization;
use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser;
use App\Users\Models\Organization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['organization_id', 'name', 'color', 'description', 'is_active'])]
class EventCategory extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsModelChanges;
    use BelongsToAuthenticatedOrganization;
    use SetsOrganizationFromAuthenticatedUser;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'category_id');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
