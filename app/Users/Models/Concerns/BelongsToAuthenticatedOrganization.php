<?php

namespace App\Users\Models\Concerns;

use App\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToAuthenticatedOrganization
{
    protected static function bootBelongsToAuthenticatedOrganization(): void
    {
        static::addGlobalScope('authenticated_organization', function (Builder $builder): void {
            $authenticatedUser = Auth::user();

            if (! $authenticatedUser instanceof User || $authenticatedUser->organization_id === null) {
                return;
            }

            $model = $builder->getModel();

            if (! $model instanceof Model) {
                return;
            }

            $builder->where(function (Builder $query) use ($model, $authenticatedUser): void {
                $query->where($model->qualifyColumn('organization_id'), $authenticatedUser->organization_id)
                    ->orWhereNull($model->qualifyColumn('organization_id'));
            });
        });
    }
}
