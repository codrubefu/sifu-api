<?php

namespace App\Service\OpenApi;

use OpenApi\Attributes as OA;

class ApiEndpoints
{
    #[OA\Get(
        path: '/services',
        summary: 'List services',
        security: [['bearerAuth' => []]],
        tags: ['Service'],
        parameters: [
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string'), example: 'enterprise'),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
            new OA\QueryParameter(name: 'is_active', required: false, schema: new OA\Schema(type: 'boolean'), example: true),
            new OA\QueryParameter(name: 'with_trashed', required: false, schema: new OA\Schema(type: 'boolean'), example: false),
            new OA\QueryParameter(name: 'only_trashed', required: false, schema: new OA\Schema(type: 'boolean'), example: false),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated service list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Service'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing services.view or services.manage right.'),
        ],
    )]
    public function index(): void
    {
    }

    #[OA\Post(
        path: '/services',
        summary: 'Create a service',
        security: [['bearerAuth' => []]],
        tags: ['Service'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreServiceRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Service created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Service'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing services.create or services.manage right.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function store(): void
    {
    }

    #[OA\Get(
        path: '/services/{service}',
        summary: 'Show a service',
        security: [['bearerAuth' => []]],
        tags: ['Service'],
        parameters: [
            new OA\PathParameter(name: 'service', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Service details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Service'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing services.view or services.manage right.'),
            new OA\Response(response: 404, description: 'Service not found.'),
        ],
    )]
    public function show(): void
    {
    }

    #[OA\Patch(
        path: '/services/{service}',
        summary: 'Update a service',
        security: [['bearerAuth' => []]],
        tags: ['Service'],
        parameters: [
            new OA\PathParameter(name: 'service', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateServiceRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Service updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Service'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing services.update or services.manage right.'),
            new OA\Response(response: 404, description: 'Service not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function update(): void
    {
    }

    #[OA\Put(
        path: '/services/{service}',
        summary: 'Replace a service',
        security: [['bearerAuth' => []]],
        tags: ['Service'],
        parameters: [
            new OA\PathParameter(name: 'service', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateServiceRequest'),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Service updated.'),
            new OA\Response(response: 403, description: 'Missing services.update or services.manage right.'),
            new OA\Response(response: 404, description: 'Service not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function replace(): void
    {
    }

    #[OA\Delete(
        path: '/services/{service}',
        summary: 'Delete a service',
        security: [['bearerAuth' => []]],
        tags: ['Service'],
        parameters: [
            new OA\PathParameter(name: 'service', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Service deleted.'),
            new OA\Response(response: 403, description: 'Missing services.delete or services.manage right.'),
            new OA\Response(response: 404, description: 'Service not found.'),
        ],
    )]
    public function destroy(): void
    {
    }

    #[OA\Post(
        path: '/services/{service}/restore',
        summary: 'Restore a deleted service',
        security: [['bearerAuth' => []]],
        tags: ['Service'],
        parameters: [
            new OA\PathParameter(name: 'service', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Service restored.'),
            new OA\Response(response: 403, description: 'Missing services.restore or services.manage right.'),
            new OA\Response(response: 404, description: 'Service not found.'),
        ],
    )]
    public function restore(): void
    {
    }

    #[OA\Patch(
        path: '/services/{service}/toggle-active',
        summary: 'Toggle service active status',
        security: [['bearerAuth' => []]],
        tags: ['Service'],
        parameters: [
            new OA\PathParameter(name: 'service', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Service active status toggled.'),
            new OA\Response(response: 403, description: 'Missing services.update or services.manage right.'),
            new OA\Response(response: 404, description: 'Service not found.'),
        ],
    )]
    public function toggleActive(): void
    {
    }

    #[OA\Post(
        path: '/service-assignments/{assignment}/activate',
        summary: 'Activate a service assignment',
        description: 'Activates a free assignment without payment, or a paid assignment only when the payment status is confirmed, belongs to the same organization, and model_type/model_id identify this exact service_user assignment. paid_at alone does not confirm payment. Lifecycle dates and activation_payment_id are committed atomically; activation notification and audit are emitted after commit.',
        security: [['bearerAuth' => []]],
        tags: ['Service'],
        parameters: [
            new OA\PathParameter(name: 'assignment', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/ActivateServiceAssignmentRequest'),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Assignment activated.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/ServiceAssignment')], type: 'object')),
            new OA\Response(response: 403, description: 'Missing services.update or services.manage right.'),
            new OA\Response(response: 404, description: 'Assignment or payment not found.'),
            new OA\Response(response: 422, description: 'Payment is required for paid services, is not confirmed, belongs to another organization, is linked to another assignment, or transition is invalid.'),
        ],
    )]
    public function activateAssignment(): void
    {
    }

    #[OA\Post(
        path: '/service-assignments/{assignment}/suspend',
        summary: 'Suspend a service assignment',
        security: [['bearerAuth' => []]],
        tags: ['Service'],
        parameters: [
            new OA\PathParameter(name: 'assignment', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SuspendServiceAssignmentRequest'),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Assignment suspended.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/ServiceAssignment')], type: 'object')),
            new OA\Response(response: 403, description: 'Missing services.update or services.manage right.'),
            new OA\Response(response: 404, description: 'Assignment not found.'),
            new OA\Response(response: 422, description: 'Validation failed or transition is invalid.'),
        ],
    )]
    public function suspendAssignment(): void
    {
    }

    #[OA\Post(
        path: '/service-assignments/{assignment}/resume',
        summary: 'Manually resume a suspended service assignment',
        security: [['bearerAuth' => []]],
        tags: ['Service'],
        parameters: [
            new OA\PathParameter(name: 'assignment', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Assignment resumed or moved to its derived terminal state.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/ServiceAssignment')], type: 'object')),
            new OA\Response(response: 403, description: 'Missing services.update or services.manage right.'),
            new OA\Response(response: 404, description: 'Assignment not found.'),
            new OA\Response(response: 422, description: 'Assignment is not suspended.'),
        ],
    )]
    public function resumeAssignment(): void
    {
    }

    #[OA\Post(
        path: '/service-assignments/{assignment}/consume',
        summary: 'Consume one access from an active service assignment',
        security: [['bearerAuth' => []]],
        tags: ['Service'],
        parameters: [
            new OA\PathParameter(name: 'assignment', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Access consumed.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/ServiceAssignment')], type: 'object')),
            new OA\Response(response: 403, description: 'Missing services.update or services.manage right.'),
            new OA\Response(response: 404, description: 'Assignment not found.'),
            new OA\Response(response: 422, description: 'Assignment is not active.'),
        ],
    )]
    public function consumeAssignmentAccess(): void
    {
    }
}
