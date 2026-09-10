<?php

namespace App\Users\Models;

use App\Users\Models\Concerns\LogsModelChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'label', 'description'])]
class Right extends Model
{
    use LogsModelChanges;


    /** @use HasFactory<\Database\Factories\Factory<static>> */
    use HasFactory;

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }
}
