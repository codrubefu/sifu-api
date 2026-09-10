<?php

namespace App\CustomFields\OpenApi;

use OpenApi\Attributes as OA;

class ApiEndpoints
{
    #[OA\Get(
        path: '/custom-fields',
        summary: 'List custom fields',
        operationId: 'listCustomFields',
        description: 'Lists custom field definitions for the authenticated organization. Pass entity_type to return cached definitions for a specific entity type sorted by sort_order and name.',
        security: [['bearerAuth' => []]],
        tags: ['Custom Fields'],
        parameters: [
            new OA\QueryParameter(
                name: 'entity_type',
                description: 'Optional entity type filter, such as contacts, companies, deals, or any future CRM entity type.',
                required: false,
                schema: new OA\Schema(type: 'string'),
                example: 'contacts',
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Custom field definitions.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldListResponse'),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsErrorResponse'),
            ),
            new OA\Response(response: 403, description: 'Missing custom-fields.view right.'),
        ],
    )]
    public function index(): void
    {
    }

    #[OA\Post(
        path: '/custom-fields',
        summary: 'Create a custom field',
        operationId: 'createCustomField',
        description: 'Creates a reusable custom field definition for the authenticated organization and clears the custom field definition cache for that entity type.',
        security: [['bearerAuth' => []]],
        tags: ['Custom Fields'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreCustomFieldRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Custom field created.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldResponse'),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsErrorResponse'),
            ),
            new OA\Response(response: 403, description: 'Missing custom-fields.manage right.'),
            new OA\Response(
                response: 422,
                description: 'Validation failed.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsValidationErrorResponse'),
            ),
        ],
    )]
    public function store(): void
    {
    }

    #[OA\Get(
        path: '/custom-fields/{customField}',
        summary: 'Retrieve a custom field',
        operationId: 'getCustomField',
        description: 'Returns one custom field definition for the authenticated organization.',
        security: [['bearerAuth' => []]],
        tags: ['Custom Fields'],
        parameters: [
            new OA\PathParameter(name: 'customField', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Custom field definition.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldResponse'),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsErrorResponse'),
            ),
            new OA\Response(response: 403, description: 'Missing custom-fields.view right.'),
            new OA\Response(
                response: 404,
                description: 'Custom field not found.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsErrorResponse'),
            ),
        ],
    )]
    public function show(): void
    {
    }

    #[OA\Put(
        path: '/custom-fields/{customField}',
        summary: 'Replace a custom field',
        operationId: 'replaceCustomField',
        description: 'Replaces an existing custom field definition for the authenticated organization and refreshes cached definitions for affected entity types.',
        security: [['bearerAuth' => []]],
        tags: ['Custom Fields'],
        parameters: [
            new OA\PathParameter(name: 'customField', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateCustomFieldRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Custom field updated.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldResponse'),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsErrorResponse'),
            ),
            new OA\Response(response: 403, description: 'Missing custom-fields.manage right.'),
            new OA\Response(
                response: 404,
                description: 'Custom field not found.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsErrorResponse'),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsValidationErrorResponse'),
            ),
        ],
    )]
    public function replace(): void
    {
    }

    #[OA\Patch(
        path: '/custom-fields/{customField}',
        summary: 'Update a custom field',
        operationId: 'updateCustomField',
        description: 'Partially updates an existing custom field definition for the authenticated organization and refreshes cached definitions for affected entity types.',
        security: [['bearerAuth' => []]],
        tags: ['Custom Fields'],
        parameters: [
            new OA\PathParameter(name: 'customField', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateCustomFieldRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Custom field updated.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldResponse'),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsErrorResponse'),
            ),
            new OA\Response(response: 403, description: 'Missing custom-fields.manage right.'),
            new OA\Response(
                response: 404,
                description: 'Custom field not found.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsErrorResponse'),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsValidationErrorResponse'),
            ),
        ],
    )]
    public function update(): void
    {
    }

    #[OA\Delete(
        path: '/custom-fields/{customField}',
        summary: 'Delete a custom field',
        operationId: 'deleteCustomField',
        description: 'Deletes the custom field definition for the authenticated organization and clears cached definitions for the field entity type.',
        security: [['bearerAuth' => []]],
        tags: ['Custom Fields'],
        parameters: [
            new OA\PathParameter(name: 'customField', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Custom field deleted.'),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsErrorResponse'),
            ),
            new OA\Response(response: 403, description: 'Missing custom-fields.manage right.'),
            new OA\Response(
                response: 404,
                description: 'Custom field not found.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsErrorResponse'),
            ),
        ],
    )]
    public function destroy(): void
    {
    }

    #[OA\Get(
        path: '/{entityType}/{entityId}/custom-field-values',
        summary: 'Retrieve entity custom field values',
        operationId: 'getEntityCustomFieldValues',
        description: 'Returns every custom field definition for the entity type along with the stored value for the requested entity, avoiding N+1 queries by loading values in bulk.',
        security: [['bearerAuth' => []]],
        tags: ['Custom Fields'],
        parameters: [
            new OA\PathParameter(name: 'entityType', required: true, schema: new OA\Schema(type: 'string'), example: 'contacts'),
            new OA\PathParameter(name: 'entityId', required: true, schema: new OA\Schema(type: 'integer'), example: 123),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Custom field values for the entity.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldValueListResponse'),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsErrorResponse'),
            ),
            new OA\Response(response: 403, description: 'Missing custom-fields.view right.'),
        ],
    )]
    public function showValues(): void
    {
    }

    #[OA\Post(
        path: '/{entityType}/{entityId}/custom-field-values',
        summary: 'Save entity custom field values',
        operationId: 'saveEntityCustomFieldValues',
        description: 'Saves custom field values by slug. Values are validated dynamically from the organization/entity field definitions and persisted into the typed EAV value column for each field type.',
        security: [['bearerAuth' => []]],
        tags: ['Custom Fields'],
        parameters: [
            new OA\PathParameter(name: 'entityType', required: true, schema: new OA\Schema(type: 'string'), example: 'contacts'),
            new OA\PathParameter(name: 'entityId', required: true, schema: new OA\Schema(type: 'integer'), example: 123),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SaveCustomFieldValuesRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Saved custom field values for the entity.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldValueListResponse'),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsErrorResponse'),
            ),
            new OA\Response(response: 403, description: 'Missing custom-fields.manage right.'),
            new OA\Response(
                response: 422,
                description: 'Validation failed.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldsValidationErrorResponse'),
            ),
        ],
    )]
    public function storeValues(): void
    {
    }
}
