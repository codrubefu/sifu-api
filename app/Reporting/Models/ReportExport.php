<?php

namespace App\Reporting\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'organization_id', 'requested_by', 'format', 'filters', 'status', 'path', 'error', 'completed_at'])]
class ReportExport extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['filters' => 'array', 'completed_at' => 'datetime'];
    }
}
