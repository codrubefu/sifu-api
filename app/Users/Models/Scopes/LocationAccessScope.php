<?php

namespace App\Users\Models\Scopes;

use App\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LocationAccessScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $authenticatedUser = Auth::user();

        if (! $authenticatedUser instanceof User) {
            return;
        }

        $locationIds = DB::table('location_user')
            ->where('user_id', $authenticatedUser->id)
            ->pluck('location_id');

        if ($locationIds->isEmpty()) {
            return;
        }

        $userIdColumn = $model->qualifyColumn('id');

        $builder->where(function (Builder $query) use ($locationIds, $userIdColumn): void {
            $query->whereNotExists(function ($query) use ($userIdColumn): void {
                $query->selectRaw('1')
                    ->from('location_user')
                    ->whereColumn('location_user.user_id', $userIdColumn);
            })->orWhereExists(function ($query) use ($locationIds, $userIdColumn): void {
                $query->selectRaw('1')
                    ->from('location_user')
                    ->whereColumn('location_user.user_id', $userIdColumn)
                    ->whereIn('location_user.location_id', $locationIds);
            });
        });
    }
}
