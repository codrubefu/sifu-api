<?php

namespace App\Events\Models;

use App\Service\Models\Service;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One service inside one OR-group of an event's service requirements.
 * Rows sharing `group_index` form an OR-group; all groups of an event are AND-ed.
 * Tenant isolation is inherited from the parent Event (always accessed through it).
 */
#[Fillable(['event_id', 'group_index', 'service_id'])]
class EventServiceRequirement extends Model
{
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    protected function casts(): array
    {
        return ['group_index' => 'integer', 'service_id' => 'integer', 'event_id' => 'integer'];
    }
}
