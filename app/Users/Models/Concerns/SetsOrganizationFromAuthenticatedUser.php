<?php

namespace App\Users\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait SetsOrganizationFromAuthenticatedUser
{
    protected static function bootSetsOrganizationFromAuthenticatedUser(): void
    {
        static::creating(function (Model $model): void {
            if (! empty($model->organization_id)) {
                return;
            }

            $organizationId = auth()->user()?->organization_id;

            if ($organizationId !== null) {
                $model->organization_id = $organizationId;
            }
        });
    }
}
