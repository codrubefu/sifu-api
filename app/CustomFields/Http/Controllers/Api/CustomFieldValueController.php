<?php

namespace App\CustomFields\Http\Controllers\Api;

use App\CustomFields\Http\Requests\SaveCustomFieldValuesRequest;
use App\CustomFields\Http\Resources\CustomFieldValueResource;
use App\CustomFields\Services\CustomFieldDefinitionService;
use App\CustomFields\Services\CustomFieldValueService;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomFieldValueController extends Controller
{
    public function __construct(
        private readonly CustomFieldDefinitionService $definitions,
        private readonly CustomFieldValueService $values,
    ) {
    }

    public function show(string $entityType, int $entityId): AnonymousResourceCollection
    {
        $organizationId = (int) auth()->user()->organization_id;
        $fields = $this->definitions->forEntityType($organizationId, $entityType);
        $values = $this->values->valuesForEntity($organizationId, $entityType, $entityId)->keyBy('custom_field_id');

        return CustomFieldValueResource::collection(
            $fields->map(fn ($field) => [
                'field' => $field,
                'value' => $values->get($field->id),
            ])->values()
        );
    }

    public function store(SaveCustomFieldValuesRequest $request, string $entityType, int $entityId): AnonymousResourceCollection
    {
        $organizationId = (int) $request->user()->organization_id;
        $this->values->saveValues($organizationId, $entityType, $entityId, $request->validated('values'));

        return $this->show($entityType, $entityId);
    }
}
