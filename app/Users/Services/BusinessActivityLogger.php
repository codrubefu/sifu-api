<?php

namespace App\Users\Services;

use App\Users\Models\AuditLog;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

class BusinessActivityLogger
{
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password', 'token', 'authorization', 'cnp', 'personal_numeric_code', 'secret',
    ];

    public function record(
        string $eventType,
        ?User $subject,
        Model $model,
        array $oldValues = [],
        array $newValues = [],
        ?User $actor = null,
    ): AuditLog {
        $actor ??= auth()->user();

        return AuditLog::query()->create([
            'organization_id' => $subject?->organization_id ?? $this->organizationId($model) ?? $actor?->organization_id,
            'subject_user_id' => $subject?->getKey(),
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'action' => str($eventType)->afterLast('.')->toString(),
            'event_type' => $eventType,
            'changed_by' => $actor?->getKey(),
            'old_values' => $this->sanitize($oldValues) ?: null,
            'new_values' => $this->sanitize($newValues) ?: null,
        ]);
    }

    public function sanitize(array $values): array
    {
        foreach ($values as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (collect(self::SENSITIVE_KEY_FRAGMENTS)->contains(
                fn (string $fragment): bool => str_contains($normalizedKey, $fragment)
            )) {
                unset($values[$key]);
            } elseif (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            }
        }

        return $values;
    }

    private function organizationId(Model $model): ?int
    {
        $value = $model->getAttribute('organization_id');

        return $value === null ? null : (int) $value;
    }
}
