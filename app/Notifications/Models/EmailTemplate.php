<?php

namespace App\Notifications\Models;

use App\Users\Models\Concerns\BelongsToAuthenticatedOrganization;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use BelongsToAuthenticatedOrganization;

    protected $fillable = ['organization_id', 'type', 'subject', 'body'];
}
