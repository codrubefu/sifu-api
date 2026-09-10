<?php

namespace App\CustomFields\Services;

use App\CustomFields\Models\CustomField;
use App\CustomFields\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CustomFieldValueService
{
    public function __construct(private readonly CustomFieldDefinitionService $definitions)
    {
    }

    public function valuesForEntity(int $organizationId, string $entityType, int $entityId): Collection
    {
        return CustomFieldValue::query()
            ->with('customField')
            ->where('organization_id', $organizationId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->get();
    }

    public function saveValues(int $organizationId, string $entityType, int $entityId, array $values): Collection
    {
        $fields = $this->definitions->forEntityType($organizationId, $entityType)->keyBy('slug');

        DB::transaction(function () use ($fields, $organizationId, $entityType, $entityId, $values): void {
            foreach ($values as $slug => $value) {
                /** @var CustomField|null $field */
                $field = $fields->get($slug);

                if (! $field) {
                    throw new InvalidArgumentException("Unknown custom field [{$slug}].");
                }

                $payload = array_merge([
                    'organization_id' => $organizationId,
                    'custom_field_id' => $field->id,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'value_text' => null,
                    'value_number' => null,
                    'value_date' => null,
                    'value_json' => null,
                ], $this->valueColumnsFor($field, $value));

                CustomFieldValue::query()->updateOrCreate([
                    'custom_field_id' => $field->id,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                ], $payload);
            }
        });

        return $this->valuesForEntity($organizationId, $entityType, $entityId);
    }

    public function fieldValue(CustomField $field, ?CustomFieldValue $value): mixed
    {
        if (! $value) {
            return null;
        }

        return match ($field->type) {
            CustomField::TYPE_NUMBER => $value->value_number === null ? null : (float) $value->value_number,
            CustomField::TYPE_DATE, CustomField::TYPE_DATETIME => $value->value_date,
            CustomField::TYPE_MULTI_SELECT, CustomField::TYPE_CHECKBOX, CustomField::TYPE_BOOLEAN => $value->value_json,
            default => $value->value_text,
        };
    }

    private function valueColumnsFor(CustomField $field, mixed $value): array
    {
        return match ($field->type) {
            CustomField::TYPE_NUMBER => ['value_number' => $value],
            CustomField::TYPE_DATE, CustomField::TYPE_DATETIME => ['value_date' => $value ? Carbon::parse($value) : null],
            CustomField::TYPE_MULTI_SELECT, CustomField::TYPE_CHECKBOX => ['value_json' => Arr::wrap($value)],
            CustomField::TYPE_BOOLEAN => ['value_json' => (bool) $value],
            default => ['value_text' => $value],
        };
    }
}
