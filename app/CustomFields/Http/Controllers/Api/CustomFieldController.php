<?php

namespace App\CustomFields\Http\Controllers\Api;

use App\CustomFields\Http\Requests\StoreCustomFieldRequest;
use App\CustomFields\Http\Requests\UpdateCustomFieldRequest;
use App\CustomFields\Http\Resources\CustomFieldResource;
use App\CustomFields\Models\CustomField;
use App\CustomFields\Services\CustomFieldDefinitionService;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomFieldController extends Controller
{
    public function __construct(private readonly CustomFieldDefinitionService $definitions)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $organizationId = (int) $request->user()->organization_id;
        $entityType = $request->string('entity_type')->toString();

        $fields = $entityType !== ''
            ? $this->definitions->forEntityType($organizationId, $entityType)
            : CustomField::query()
                ->where('organization_id', $organizationId)
                ->orderBy('entity_type')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

        return CustomFieldResource::collection($fields);
    }

    public function store(StoreCustomFieldRequest $request): JsonResponse
    {
        $field = CustomField::query()->create(array_merge($request->validated(), [
            'organization_id' => $request->user()->organization_id,
        ]));

        $this->definitions->clearCache($field->organization_id, $field->entity_type);

        return (new CustomFieldResource($field))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CustomField $customField): CustomFieldResource
    {
        return new CustomFieldResource($customField);
    }

    public function update(UpdateCustomFieldRequest $request, CustomField $customField): CustomFieldResource
    {
        $previousEntityType = $customField->entity_type;

        $customField->update($request->validated());

        $this->definitions->clearCache($customField->organization_id, $previousEntityType);
        $this->definitions->clearCache($customField->organization_id, $customField->entity_type);

        return new CustomFieldResource($customField);
    }

    public function destroy(CustomField $customField): JsonResponse
    {
        $organizationId = $customField->organization_id;
        $entityType = $customField->entity_type;

        $customField->delete();
        $this->definitions->clearCache($organizationId, $entityType);

        return response()->json(status: 204);
    }
}
