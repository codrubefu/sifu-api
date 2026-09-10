<?php

namespace App\Users\Services;

use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrganizationAccessService
{
    public function deleteManyToManySetting(
        ?int $organizationId,
        string $model,
        string $relationOrTable,
    ): bool {
        if ($organizationId === null) {
            return true;
        }

        return (bool) config("organization-access.settings.{$organizationId}.delete_{$model}.{$relationOrTable}", true);
    }

    public function blockedManyToManyDeleteRelation(
        Model $model,
        array $relations,
        ?int $organizationId = null,
        ?string $modelKey = null,
    ): ?string {
        $modelKey ??= Str::snake(class_basename($model));
        $organizationId ??= $this->organizationIdForModel($model);

        foreach ($relations as $relation) {
            $relationQuery = $model->{$relation}();
            $pivotTable = $relationQuery instanceof BelongsToMany ? $relationQuery->getTable() : null;

            if (
                $this->deleteManyToManySettingsAllowDelete($organizationId, $modelKey, $relation)
                && ($pivotTable === null || $this->deleteManyToManySettingsAllowDelete($organizationId, $modelKey, $pivotTable))
            ) {
                continue;
            }

            if ($relationQuery instanceof BelongsToMany && $relationQuery->withoutGlobalScopes()->exists()) {
                return $relation;
            }
        }

        return null;
    }

    public function deleteBlockedByManyToManyResponse(
        Model $model,
        array $relations,
        ?int $organizationId = null,
        ?string $modelKey = null,
    ): ?array {
        $relation = $this->blockedManyToManyDeleteRelation($model, $relations, $organizationId, $modelKey);

        if ($relation === null) {
            return null;
        }

        return [
            'message' => sprintf(
                'Cannot delete %s because it still has related %s.',
                Str::headline(class_basename($model)),
                Str::headline($relation),
            ),
        ];
    }

    public function disabledPatternsForOrganization(?int $organizationId): array
    {
        if ($organizationId === null) {
            return [];
        }

        $disabledGroups = config("organization-access.disabled_right_groups.{$organizationId}", []);
        $rightGroups = config('organization-access.right_groups', []);

        return collect(Arr::wrap($disabledGroups))
            ->flatMap(fn (string $group): array => Arr::wrap($rightGroups[$group] ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isRightDisabledForOrganization(string $rightName, ?int $organizationId): bool
    {
        foreach ($this->disabledPatternsForOrganization($organizationId) as $pattern) {
            if (Str::is($pattern, $rightName)) {
                return true;
            }
        }

        return false;
    }

    public function availableRightNames(array $rightNames, ?int $organizationId): array
    {
        return collect($rightNames)
            ->reject(fn (string $rightName): bool => $this->isRightDisabledForOrganization($rightName, $organizationId))
            ->values()
            ->all();
    }

    public function applyAvailableRightsFilter(Builder|Relation $query, ?int $organizationId): Builder|Relation
    {
        foreach ($this->disabledPatternsForOrganization($organizationId) as $pattern) {
            $query->where('name', 'not like', $this->patternToSqlLike($pattern));
        }

        return $query;
    }

    public function disabledRightIds(Collection $rightIds, ?int $organizationId): Collection
    {
        if ($rightIds->isEmpty()) {
            return collect();
        }

        return Right::query()
            ->whereIn('id', $rightIds->all())
            ->get(['id', 'name'])
            ->filter(fn (Right $right): bool => $this->isRightDisabledForOrganization($right->name, $organizationId))
            ->pluck('id')
            ->values();
    }

    public function loadUserAccessRelations(User $user): User
    {
        return $user->load([
            'groups.rights' => fn ($query) => $this->applyAvailableRightsFilter($query, $user->organization_id),
            'locations',
            'activeServices',
        ]);
    }

    private function patternToSqlLike(string $pattern): string
    {
        return str_replace('*', '%', $pattern);
    }

    private function deleteManyToManySettingsAllowDelete(
        ?int $organizationId,
        string $modelKey,
        string $relationOrTable,
    ): bool {
        foreach ($this->deleteModelConfigKeys($modelKey) as $configModelKey) {
            if (! $this->deleteManyToManySetting($organizationId, $configModelKey, $relationOrTable)) {
                return false;
            }
        }

        return true;
    }

    private function deleteModelConfigKeys(string $modelKey): array
    {
        return collect([$modelKey, Str::plural($modelKey)])
            ->unique()
            ->values()
            ->all();
    }

    private function organizationIdForModel(Model $model): ?int
    {
        $organizationId = $model->getAttribute('organization_id');

        return $organizationId === null ? null : (int) $organizationId;
    }
}
