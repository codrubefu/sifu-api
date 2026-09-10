<?php

namespace App\Users\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'user_id', 'type', 'status', 'details', 'requested_by', 'processed_by', 'processed_at', 'subject_fingerprint', 'execution_proof'])]
class GdprRequest extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['details' => 'array', 'execution_proof' => 'array', 'processed_at' => 'immutable_datetime'];
    }
}
