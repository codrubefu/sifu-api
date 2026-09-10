<?php

namespace App\Users\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'user_id', 'gdpr_request_id', 'status', 'disk', 'path', 'expires_at', 'failure_reason'])]
class GdprExport extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }
}
