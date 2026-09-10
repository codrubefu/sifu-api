<?php

namespace App\Users\Models\Concerns;

use App\Users\Models\AuditLog;
use App\Users\Models\User;
use App\Users\Services\BusinessActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait LogsModelChanges
{
    protected static function bootLogsModelChanges(): void
    {
        static::created(function (Model $model): void {
            static::writeAuditLog($model, 'created', null, static::visibleAttributes($model));
        });

        static::updated(function (Model $model): void {
            $newValues = Arr::only(static::visibleAttributes($model), array_keys($model->getChanges()));
            unset($newValues['updated_at']);

            if ($newValues === []) {
                return;
            }

            $oldValues = [];

            foreach (array_keys($newValues) as $attribute) {
                $oldValues[$attribute] = $model->getOriginal($attribute);
            }

            static::writeAuditLog($model, 'updated', $oldValues, $newValues);

            if ($model instanceof User && array_key_exists('active', $newValues) && $newValues['active'] === true) {
                app(BusinessActivityLogger::class)->record(AuditLog::APPROVAL_GRANTED, $model, $model, $oldValues, ['active' => true]);
            }

            if ($model instanceof User && array_key_exists('user_code', $newValues) && filled($newValues['user_code'])) {
                app(BusinessActivityLogger::class)->record(AuditLog::CARD_ISSUED, $model, $model, [], ['user_code' => $newValues['user_code']]);
            }
        });

        static::deleted(function (Model $model): void {
            static::writeAuditLog($model, 'deleted', static::visibleAttributes($model), null);
        });
    }

    protected static function writeAuditLog(Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        $eventType = $model instanceof User
            ? ($action === 'created' ? AuditLog::USER_CREATED : ($action === 'updated' ? AuditLog::USER_UPDATED : "user.{$action}"))
            : class_basename($model).".{$action}";

        $subject = $model instanceof User && $action !== 'deleted' ? $model : null;

        app(BusinessActivityLogger::class)->record(
            strtolower($eventType),
            $subject,
            $model,
            $oldValues ?? [],
            $newValues ?? [],
        );
    }

    protected static function visibleAttributes(Model $model): array
    {
        return Arr::except($model->attributesToArray(), array_unique(array_merge(
            $model->getHidden(),
            ['cnp', 'password', 'remember_token', 'token', 'access_token', 'refresh_token', 'push_token', 'provider_payload', 'payload', 'secret', 'client_secret'],
        )));
    }
}
