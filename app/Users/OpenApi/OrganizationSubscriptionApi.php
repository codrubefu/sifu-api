<?php

namespace App\Users\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'OrganizationLimitError', required: ['message', 'code', 'resource', 'limit', 'used', 'requested', 'projected', 'period'], properties: [
    new OA\Property(property: 'message', type: 'string', example: 'Limita de administratori a organizației a fost atinsă.'),
    new OA\Property(property: 'code', type: 'string', enum: ['organization_limit_exceeded', 'organization_limit_available']),
    new OA\Property(property: 'resource', type: 'string', enum: ['members', 'locations', 'events', 'administrators']),
    new OA\Property(property: 'limit', type: 'integer', nullable: true, example: 2),
    new OA\Property(property: 'used', type: 'integer', example: 2),
    new OA\Property(property: 'requested', type: 'integer', example: 1),
    new OA\Property(property: 'projected', type: 'integer', example: 3),
    new OA\Property(property: 'period', type: 'string', nullable: true, example: null, description: 'YYYY-MM for events; null for other resources.'),
], type: 'object')]
#[OA\Schema(schema: 'OrganizationLimitUsage', required: ['limit', 'source', 'used', 'remaining', 'exceeded'], properties: [
    new OA\Property(property: 'limit', type: 'integer', minimum: 0, nullable: true, description: 'null means unlimited; zero disallows usage.'),
    new OA\Property(property: 'source', type: 'string', enum: ['plan', 'override']),
    new OA\Property(property: 'used', type: 'integer'),
    new OA\Property(property: 'remaining', type: 'integer', nullable: true),
    new OA\Property(property: 'exceeded', type: 'boolean'),
], type: 'object')]
#[OA\Schema(schema: 'OrganizationSubscription', required: ['plan', 'limits', 'month', 'event_usage_basis', 'event_generation_blocks'], properties: [
    new OA\Property(property: 'plan', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'start'),
        new OA\Property(property: 'name', type: 'string', example: 'Start'),
        new OA\Property(property: 'monthly_price_cents', type: 'integer', example: 3000),
        new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
    ], type: 'object'),
    new OA\Property(property: 'limits', properties: [
        new OA\Property(property: 'members', ref: '#/components/schemas/OrganizationLimitUsage'),
        new OA\Property(property: 'locations', ref: '#/components/schemas/OrganizationLimitUsage'),
        new OA\Property(property: 'events', ref: '#/components/schemas/OrganizationLimitUsage'),
        new OA\Property(property: 'administrators', ref: '#/components/schemas/OrganizationLimitUsage'),
    ], type: 'object'),
    new OA\Property(property: 'month', type: 'string', example: '2026-09'),
    new OA\Property(property: 'event_usage_basis', type: 'string', enum: ['generated_occurrences']),
    new OA\Property(property: 'event_generation_blocks', type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'event_id', type: 'integer'),
        new OA\Property(property: 'details', ref: '#/components/schemas/OrganizationLimitError'),
    ], type: 'object')),
], type: 'object')]
class OrganizationSubscriptionApi
{
    #[OA\Get(path: '/organization/subscription', summary: 'Read organization subscription and usage',
        description: 'Requires organization_subscription.view. Uses the authenticated organization; limits can only be changed through CLI or DB. Counts all organization locations, active clients and active administrators. Event usage counts generated scheduled/completed occurrences by occurrence_date, not the entire future recurrence. Defaults to the current month in the application timezone.',
        security: [['bearerAuth' => []]], tags: ['Organization Subscription'],
        parameters: [new OA\QueryParameter(name: 'month', schema: new OA\Schema(type: 'string', pattern: '^\\d{4}-(0[1-9]|1[0-2])$', example: '2026-09'))],
        responses: [
            new OA\Response(response: 200, description: 'Effective limits, usage and blocked recurrence extensions.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/OrganizationSubscription')])),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Missing organization_subscription.view.'),
            new OA\Response(response: 422, description: 'Invalid month or prohibited organization_id.'),
            new OA\Response(response: 503, description: 'Missing organization plan configuration.'),
        ])]
    public function show(): void {}

    #[OA\Post(path: '/organization/subscription/check', summary: 'Check available organization quota without reserving capacity',
        description: 'Requires organization_subscription.view. Checks commercial quota only. A successful check does not reserve capacity or grant an operation right. Actual writes recheck transactionally and return HTTP 409 on quota growth above the limit. A negative preflight returns HTTP 200 with allowed=false.',
        security: [['bearerAuth' => []]], tags: ['Organization Subscription'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['resource', 'quantity'], properties: [
            new OA\Property(property: 'resource', type: 'string', enum: ['members', 'locations', 'events', 'administrators']),
            new OA\Property(property: 'quantity', type: 'integer', minimum: 1, maximum: 2147483647, example: 1),
            new OA\Property(property: 'month', type: 'string', nullable: true, example: '2026-09', description: 'Required for events.'),
        ])), responses: [
            new OA\Response(response: 200, description: 'Quota verdict.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', allOf: [new OA\Schema(ref: '#/components/schemas/OrganizationLimitError'), new OA\Schema(properties: [new OA\Property(property: 'allowed', type: 'boolean')], type: 'object')])])),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Missing organization_subscription.view.'),
            new OA\Response(response: 422, description: 'Invalid resource, quantity, month or prohibited organization_id.'),
            new OA\Response(response: 503, description: 'Missing organization plan configuration.'),
        ])]
    public function check(): void {}

    #[OA\Post(path: '/clients', summary: 'Post clients (user API alias)', description: 'Requires users.manage. Group rights determine client/administrator classification; route name does not determine it. Enforces organization member/administrator limits.', security: [['bearerAuth' => []]], tags: ['Users'], 
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreUserRequest')),
        responses: [new OA\Response(response: 201, description: 'User saved.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])), new OA\Response(response: 401, description: 'Unauthenticated.'), new OA\Response(response: 403, description: 'Missing users.manage.'), new OA\Response(response: 404, description: 'User not found in organization.'), new OA\Response(response: 422, description: 'Validation failed.'), new OA\Response(response: 409, description: 'Organization quota exceeded.', content: new OA\JsonContent(ref: '#/components/schemas/OrganizationLimitError'))])]
    public function postClients(): void {}

    #[OA\Put(path: '/clients/{user}', summary: 'Put clients (user API alias)', description: 'Requires users.manage. Group rights determine client/administrator classification; route name does not determine it. Enforces organization member/administrator limits.', security: [['bearerAuth' => []]], tags: ['Users'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateUserRequest')),
        responses: [new OA\Response(response: 200, description: 'User saved.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])), new OA\Response(response: 401, description: 'Unauthenticated.'), new OA\Response(response: 403, description: 'Missing users.manage.'), new OA\Response(response: 404, description: 'User not found in organization.'), new OA\Response(response: 422, description: 'Validation failed.'), new OA\Response(response: 409, description: 'Organization quota exceeded.', content: new OA\JsonContent(ref: '#/components/schemas/OrganizationLimitError'))])]
    public function putClients(): void {}

    #[OA\Patch(path: '/clients/{user}', summary: 'Patch clients (user API alias)', description: 'Requires users.manage. Group rights determine client/administrator classification; route name does not determine it. Enforces organization member/administrator limits.', security: [['bearerAuth' => []]], tags: ['Users'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateUserRequest')),
        responses: [new OA\Response(response: 200, description: 'User saved.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])), new OA\Response(response: 401, description: 'Unauthenticated.'), new OA\Response(response: 403, description: 'Missing users.manage.'), new OA\Response(response: 404, description: 'User not found in organization.'), new OA\Response(response: 422, description: 'Validation failed.'), new OA\Response(response: 409, description: 'Organization quota exceeded.', content: new OA\JsonContent(ref: '#/components/schemas/OrganizationLimitError'))])]
    public function patchClients(): void {}

    #[OA\Post(path: '/administrators', summary: 'Post administrators (user API alias)', description: 'Requires users.manage. Group rights determine client/administrator classification; route name does not determine it. Enforces organization member/administrator limits.', security: [['bearerAuth' => []]], tags: ['Users'], 
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreUserRequest')),
        responses: [new OA\Response(response: 201, description: 'User saved.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])), new OA\Response(response: 401, description: 'Unauthenticated.'), new OA\Response(response: 403, description: 'Missing users.manage.'), new OA\Response(response: 404, description: 'User not found in organization.'), new OA\Response(response: 422, description: 'Validation failed.'), new OA\Response(response: 409, description: 'Organization quota exceeded.', content: new OA\JsonContent(ref: '#/components/schemas/OrganizationLimitError'))])]
    public function postAdministrators(): void {}

    #[OA\Put(path: '/administrators/{user}', summary: 'Put administrators (user API alias)', description: 'Requires users.manage. Group rights determine client/administrator classification; route name does not determine it. Enforces organization member/administrator limits.', security: [['bearerAuth' => []]], tags: ['Users'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateUserRequest')),
        responses: [new OA\Response(response: 200, description: 'User saved.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])), new OA\Response(response: 401, description: 'Unauthenticated.'), new OA\Response(response: 403, description: 'Missing users.manage.'), new OA\Response(response: 404, description: 'User not found in organization.'), new OA\Response(response: 422, description: 'Validation failed.'), new OA\Response(response: 409, description: 'Organization quota exceeded.', content: new OA\JsonContent(ref: '#/components/schemas/OrganizationLimitError'))])]
    public function putAdministrators(): void {}

    #[OA\Patch(path: '/administrators/{user}', summary: 'Patch administrators (user API alias)', description: 'Requires users.manage. Group rights determine client/administrator classification; route name does not determine it. Enforces organization member/administrator limits.', security: [['bearerAuth' => []]], tags: ['Users'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateUserRequest')),
        responses: [new OA\Response(response: 200, description: 'User saved.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])), new OA\Response(response: 401, description: 'Unauthenticated.'), new OA\Response(response: 403, description: 'Missing users.manage.'), new OA\Response(response: 404, description: 'User not found in organization.'), new OA\Response(response: 422, description: 'Validation failed.'), new OA\Response(response: 409, description: 'Organization quota exceeded.', content: new OA\JsonContent(ref: '#/components/schemas/OrganizationLimitError'))])]
    public function patchAdministrators(): void {}
}
