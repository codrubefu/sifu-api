<?php

namespace App\Users\Models;

use App\Users\Models\Organization;

use App\Articles\Models\Article;
use App\Users\Models\Concerns\BelongsToAuthenticatedOrganization;
use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'description', 'organization_id', 'location_group_id'])]
class Location extends Model
{
    use LogsModelChanges;
    use BelongsToAuthenticatedOrganization;
    use SetsOrganizationFromAuthenticatedUser;


    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    /** @use HasFactory<\Database\Factories\Factory<static>> */
    use HasFactory;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class)->withTimestamps();
    }

    public function locationGroup(): BelongsTo
    {
        return $this->belongsTo(LocationGroup::class);
    }
}
