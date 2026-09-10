<?php

namespace App\Users\OpenApi;

use OpenApi\Attributes as OA;

class ApiEndpoints
{
    #[OA\Post(
        path: '/login',
        summary: 'Login and issue an expiring bearer token',
        description: 'Authentication is organization-aware. Issued tokens expire after the configured bearer-token lifetime. Attempts are rate limited by client IP, organization and a hash of the declared email; authentication logs never contain the password or issued token.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'organization_id', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'test@example.com'),
                    new OA\Property(property: 'organization_id', type: 'integer', example: 1),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Bearer token issued.'),
            new OA\Response(response: 401, description: 'Invalid credentials.'),
            new OA\Response(response: 429, description: 'Login rate limit exceeded for this IP, organization and declared identity.'),
        ],
    )]
    public function login(): void
    {
    }

    #[OA\Get(
        path: '/organizations/slug/{slug}',
        summary: 'Find organization by slug',
        tags: ['Auth'],
        parameters: [
            new OA\PathParameter(
                name: 'slug',
                required: true,
                schema: new OA\Schema(type: 'string'),
                example: 'acme',
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization found.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'slug', type: 'string', example: 'acme'),
                                new OA\Property(property: 'url', type: 'string', nullable: true, description: 'Frontend URL for this organization; used to build links sent by e-mail.', example: 'https://acme.example.com'),
                                new OA\Property(property: 'name', type: 'string', example: 'Acme SRL'),
                                new OA\Property(property: 'address', type: 'string', nullable: true, example: 'Str. Exemplu 1, Bucuresti'),
                                new OA\Property(property: 'email', type: 'string', nullable: true, example: 'office@acme.test'),
                                new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+40740111222'),
                                new OA\Property(property: 'web', type: 'string', nullable: true, example: 'https://acme.test'),
                                new OA\Property(property: 'cui', type: 'string', nullable: true, example: 'RO12345678'),
                                new OA\Property(property: 'nr_reg_com', type: 'string', nullable: true, example: 'J40/1234/2026'),
                                new OA\Property(property: 'capital', type: 'string', nullable: true, example: '200 RON'),
                                new OA\Property(property: 'cont', type: 'string', nullable: true, example: 'RO49AAAA1B31007593840000'),
                                new OA\Property(property: 'bank', type: 'string', nullable: true, example: 'Banca Exemplu'),
                                new OA\Property(property: 'receipt_code', type: 'string', example: 'CH'),
                                new OA\Property(property: 'receipt_number', type: 'integer', example: 0),
                                new OA\Property(property: 'invoice_code', type: 'string', example: 'INV'),
                                new OA\Property(property: 'invoice_number', type: 'integer', example: 0),
                                new OA\Property(property: 'bill_code', type: 'string', example: 'BILL'),
                                new OA\Property(property: 'bill_number', type: 'integer', example: 0),
                            ],
                            type: 'object',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 404, description: 'Organization not found.'),
        ],
    )]
    public function organizationBySlug(): void
    {
    }

    #[OA\Get(
        path: '/me',
        summary: 'Get the authenticated user',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated user.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
        ],
    )]
    public function me(): void
    {
    }

    #[OA\Patch(
        path: '/me/password',
        summary: 'Update the authenticated user password and revoke sessions',
        description: 'Applies the configured operator or administrator password policy, including the compromised-password check. A successful password change revokes every bearer token for the user, including the token used for this request.',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateMePasswordRequest'),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password updated.', content: new OA\JsonContent(ref: '#/components/schemas/StandardSuccessResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 422, description: 'Validation failed.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function mePassword(): void
    {
    }

    #[OA\Get(
        path: '/me/custom-fields',
        summary: 'List authenticated user custom fields',
        description: 'Returns every custom field definition for the users entity type along with the stored value for the authenticated user.',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Custom field values for the authenticated user.',
                content: new OA\JsonContent(ref: '#/components/schemas/CustomFieldValueListResponse'),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
        ],
    )]
    public function meCustomFields(): void
    {
    }

    #[OA\Get(
        path: '/me/events',
        summary: 'List authenticated user event occurrences',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        parameters: [
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated event occurrences attached to the authenticated user.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/EventOccurrence'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
        ],
    )]
    public function meEvents(): void
    {
    }

    #[OA\Get(
        path: '/me/services',
        summary: 'List authenticated user services',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        parameters: [
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated services attached to the authenticated user.',
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
            new OA\Response(response: 401, description: 'Unauthenticated.'),
        ],
    )]
    public function meServices(): void
    {
    }

    #[OA\Post(
        path: '/logout',
        summary: 'Revoke the current bearer token',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Logged out.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
        ],
    )]
    public function logout(): void
    {
    }

    #[OA\Get(
        path: '/administrators',
        summary: 'List administrator users',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\QueryParameter(
                name: 'search',
                description: 'Search by first name, last name, email, phone, or user code.',
                required: false,
                schema: new OA\Schema(type: 'string'),
                example: 'USR00000000000000000000000000001',
            ),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated administrator user list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/User'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing users.view right.'),
        ],
    )]
    public function administratorsIndex(): void
    {
    }

    #[OA\Get(
        path: '/clients',
        summary: 'List client users',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\QueryParameter(
                name: 'search',
                description: 'Search by first name, last name, email, phone, or user code.',
                required: false,
                schema: new OA\Schema(type: 'string'),
                example: 'USR00000000000000000000000000001',
            ),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated client user list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/User'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing users.view right.'),
        ],
    )]
    public function clientsIndex(): void
    {
    }

    #[OA\Get(
        path: '/groups',
        summary: 'List user groups',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string'), example: 'admin'),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated group list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Group'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing groups.view right.'),
        ],
    )]
    public function groupsIndex(): void
    {
    }

    #[OA\Post(
        path: '/groups',
        summary: 'Create a user group',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreGroupRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Group created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Group'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing groups.manage right.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function groupsStore(): void
    {
    }

    #[OA\Get(
        path: '/groups/{group}',
        summary: 'Show a user group',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\PathParameter(name: 'group', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Group details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Group'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing groups.view right.'),
            new OA\Response(response: 404, description: 'Group not found.'),
        ],
    )]
    public function groupsShow(): void
    {
    }

    #[OA\Patch(
        path: '/groups/{group}',
        summary: 'Update a user group',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\PathParameter(name: 'group', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateGroupRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Group updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Group'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing groups.manage right.'),
            new OA\Response(response: 404, description: 'Group not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function groupsUpdate(): void
    {
    }

    #[OA\Put(
        path: '/groups/{group}',
        summary: 'Replace a user group',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\PathParameter(name: 'group', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateGroupRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Group updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Group'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing groups.manage right.'),
            new OA\Response(response: 404, description: 'Group not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function groupsReplace(): void
    {
    }

    #[OA\Delete(
        path: '/groups/{group}',
        summary: 'Delete a user group',
        security: [['bearerAuth' => []]],
        tags: ['Groups'],
        parameters: [
            new OA\PathParameter(name: 'group', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Group deleted.'),
            new OA\Response(response: 403, description: 'Missing groups.manage right.'),
            new OA\Response(response: 404, description: 'Group not found.'),
            new OA\Response(response: 422, description: 'Cannot delete a group that still has users.'),
        ],
    )]
    public function groupsDestroy(): void
    {
    }

    #[OA\Get(
        path: '/rights',
        summary: 'List rights',
        security: [['bearerAuth' => []]],
        tags: ['Rights'],
        parameters: [
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string'), example: 'users'),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated right list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Right'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing rights.view right.'),
        ],
    )]
    public function rightsIndex(): void
    {
    }

    #[OA\Post(
        path: '/rights',
        summary: 'Create a right',
        security: [['bearerAuth' => []]],
        tags: ['Rights'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreRightRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Right created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Right'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing rights.manage right.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function rightsStore(): void
    {
    }

    #[OA\Get(
        path: '/rights/{right}',
        summary: 'Show a right',
        security: [['bearerAuth' => []]],
        tags: ['Rights'],
        parameters: [
            new OA\PathParameter(name: 'right', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Right details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Right'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing rights.view right.'),
            new OA\Response(response: 404, description: 'Right not found.'),
        ],
    )]
    public function rightsShow(): void
    {
    }

    #[OA\Patch(
        path: '/rights/{right}',
        summary: 'Update a right',
        security: [['bearerAuth' => []]],
        tags: ['Rights'],
        parameters: [
            new OA\PathParameter(name: 'right', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateRightRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Right updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Right'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing rights.manage right.'),
            new OA\Response(response: 404, description: 'Right not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function rightsUpdate(): void
    {
    }

    #[OA\Put(
        path: '/rights/{right}',
        summary: 'Replace a right',
        security: [['bearerAuth' => []]],
        tags: ['Rights'],
        parameters: [
            new OA\PathParameter(name: 'right', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateRightRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Right updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Right'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing rights.manage right.'),
            new OA\Response(response: 404, description: 'Right not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function rightsReplace(): void
    {
    }

    #[OA\Delete(
        path: '/rights/{right}',
        summary: 'Delete a right',
        security: [['bearerAuth' => []]],
        tags: ['Rights'],
        parameters: [
            new OA\PathParameter(name: 'right', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Right deleted.'),
            new OA\Response(response: 403, description: 'Missing rights.manage right.'),
            new OA\Response(response: 404, description: 'Right not found.'),
            new OA\Response(response: 422, description: 'Cannot delete a right assigned to groups.'),
        ],
    )]
    public function rightsDestroy(): void
    {
    }


    #[OA\Get(
        path: '/location-groups',
        summary: 'List location groups',
        security: [['bearerAuth' => []]],
        tags: ['Location Groups'],
        parameters: [
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string'), example: 'north'),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated location group list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/LocationGroup'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing locations.view right.'),
        ],
    )]
    public function locationGroupsIndex(): void
    {
    }

    #[OA\Post(
        path: '/location-groups',
        summary: 'Create a location group',
        security: [['bearerAuth' => []]],
        tags: ['Location Groups'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreLocationGroupRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Location group created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/LocationGroup'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing locations.manage right.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function locationGroupsStore(): void
    {
    }

    #[OA\Get(
        path: '/location-groups/{locationGroup}',
        summary: 'Show a location group',
        security: [['bearerAuth' => []]],
        tags: ['Location Groups'],
        parameters: [
            new OA\PathParameter(name: 'locationGroup', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Location group details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/LocationGroup'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing locations.view right.'),
            new OA\Response(response: 404, description: 'Location group not found.'),
        ],
    )]
    public function locationGroupsShow(): void
    {
    }

    #[OA\Patch(
        path: '/location-groups/{locationGroup}',
        summary: 'Update a location group',
        security: [['bearerAuth' => []]],
        tags: ['Location Groups'],
        parameters: [
            new OA\PathParameter(name: 'locationGroup', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateLocationGroupRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Location group updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/LocationGroup'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing locations.manage right.'),
            new OA\Response(response: 404, description: 'Location group not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function locationGroupsUpdate(): void
    {
    }

    #[OA\Put(
        path: '/location-groups/{locationGroup}',
        summary: 'Replace a location group',
        security: [['bearerAuth' => []]],
        tags: ['Location Groups'],
        parameters: [
            new OA\PathParameter(name: 'locationGroup', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateLocationGroupRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Location group updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/LocationGroup'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing locations.manage right.'),
            new OA\Response(response: 404, description: 'Location group not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function locationGroupsReplace(): void
    {
    }

    #[OA\Delete(
        path: '/location-groups/{locationGroup}',
        summary: 'Delete a location group',
        security: [['bearerAuth' => []]],
        tags: ['Location Groups'],
        parameters: [
            new OA\PathParameter(name: 'locationGroup', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Location group deleted.'),
            new OA\Response(response: 403, description: 'Missing locations.manage right.'),
            new OA\Response(response: 404, description: 'Location group not found.'),
        ],
    )]
    public function locationGroupsDestroy(): void
    {
    }

    #[OA\Get(
        path: '/locations',
        summary: 'List locations',
        security: [['bearerAuth' => []]],
        tags: ['Locations'],
        parameters: [
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string'), example: 'office'),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated location list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Location'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing locations.view right.'),
        ],
    )]
    public function locationsIndex(): void
    {
    }

    #[OA\Post(
        path: '/locations',
        summary: 'Create a location',
        security: [['bearerAuth' => []]],
        tags: ['Locations'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreLocationRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Location created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Location'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing locations.manage right.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function locationsStore(): void
    {
    }

    #[OA\Get(
        path: '/locations/{location}',
        summary: 'Show a location',
        security: [['bearerAuth' => []]],
        tags: ['Locations'],
        parameters: [
            new OA\PathParameter(name: 'location', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Location details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Location'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing locations.view right.'),
            new OA\Response(response: 404, description: 'Location not found.'),
        ],
    )]
    public function locationsShow(): void
    {
    }

    #[OA\Patch(
        path: '/locations/{location}',
        summary: 'Update a location',
        security: [['bearerAuth' => []]],
        tags: ['Locations'],
        parameters: [
            new OA\PathParameter(name: 'location', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateLocationRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Location updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Location'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing locations.manage right.'),
            new OA\Response(response: 404, description: 'Location not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function locationsUpdate(): void
    {
    }

    #[OA\Put(
        path: '/locations/{location}',
        summary: 'Replace a location',
        security: [['bearerAuth' => []]],
        tags: ['Locations'],
        parameters: [
            new OA\PathParameter(name: 'location', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateLocationRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Location updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Location'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing locations.manage right.'),
            new OA\Response(response: 404, description: 'Location not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function locationsReplace(): void
    {
    }

    #[OA\Delete(
        path: '/locations/{location}',
        summary: 'Delete a location',
        security: [['bearerAuth' => []]],
        tags: ['Locations'],
        parameters: [
            new OA\PathParameter(name: 'location', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Location deleted.'),
            new OA\Response(response: 403, description: 'Missing locations.manage right.'),
            new OA\Response(response: 404, description: 'Location not found.'),
        ],
    )]
    public function locationsDestroy(): void
    {
    }

    #[OA\Get(
        path: '/users',
        summary: 'List users',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\QueryParameter(
                name: 'search',
                description: 'Search by first name, last name, email, phone, or user code.',
                required: false,
                schema: new OA\Schema(type: 'string'),
                example: 'USR00000000000000000000000000001',
            ),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated user list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/User'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing users.view right.'),
        ],
    )]
    public function usersIndex(): void
    {
    }

    #[OA\Get(
        path: '/users/search/user-code',
        summary: 'Search users by user code',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\QueryParameter(
                name: 'search',
                description: 'Search only by user code.',
                required: false,
                schema: new OA\Schema(type: 'string'),
                example: '111',
            ),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated user list filtered only by user code.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/User'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing users.view right.'),
        ],
    )]
    public function usersSearchByUserCode(): void
    {
    }

    #[OA\Post(
        path: '/users',
        summary: 'Create a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreUserRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing users.manage right.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function usersStore(): void
    {
    }

    #[OA\Get(
        path: '/users/{user}',
        summary: 'Show a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing users.view right.'),
            new OA\Response(response: 404, description: 'User not found.'),
        ],
    )]
    public function usersShow(): void
    {
    }

    #[OA\Get(
        path: '/users/{user}/activity',
        summary: 'List a user activity journal',
        description: 'Returns the organization-scoped business activity for the selected member, ordered newest first. Audit values are sanitized and never contain passwords, tokens, CNP or other secrets.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\PathParameter(
                name: 'user',
                description: 'User whose business activity is requested.',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 35,
            ),
            new OA\QueryParameter(
                name: 'type',
                description: 'Filter by an exact business event identifier.',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    enum: [
                        'user.created',
                        'user.updated',
                        'service.assigned',
                        'service.renewed',
                        'service.suspended',
                        'payment.recorded',
                        'approval.granted',
                        'card.issued',
                        'sms.sent',
                    ],
                ),
                example: 'payment.recorded',
            ),
            new OA\QueryParameter(name: 'from', description: 'Include activity on or after this date.', required: false, schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-08-01'),
            new OA\QueryParameter(name: 'to', description: 'Include activity on or before this date.', required: false, schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-08-31'),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15), example: 25),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated activity journal, isolated to the authenticated organization.',
                content: new OA\JsonContent(ref: '#/components/schemas/UserActivityPage'),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Missing users.view right.'),
            new OA\Response(response: 404, description: 'User not found in the authenticated organization.'),
            new OA\Response(response: 422, description: 'Invalid type, period, or pagination parameters.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function usersActivity(): void
    {
    }

    #[OA\Patch(
        path: '/users/{user}',
        summary: 'Update a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateUserRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing users.manage right.'),
            new OA\Response(response: 404, description: 'User not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function usersUpdate(): void
    {
    }

    #[OA\Patch(
        path: '/users/service/{user}',
        summary: 'Sync services for a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SyncUserServicesRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User services synced.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing users.manage right.'),
            new OA\Response(response: 404, description: 'User not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function usersSyncServices(): void
    {
    }

    #[OA\Put(
        path: '/users/{user}',
        summary: 'Replace a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateUserRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing users.manage right.'),
            new OA\Response(response: 404, description: 'User not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function usersReplace(): void
    {
    }

    #[OA\Delete(
        path: '/users/{user}',
        summary: 'Execute the GDPR retention and erasure workflow for a user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Documents deleted, activity anonymized, financial records minimized, and account anonymized.'),
            new OA\Response(response: 403, description: 'Missing users.manage right.'),
            new OA\Response(response: 404, description: 'User not found.'),
            new OA\Response(response: 422, description: 'Cannot delete your own user account.'),
        ],
    )]
    public function usersDestroy(): void
    {
    }

    #[OA\Get(path: '/me/privacy/data', summary: 'Access own personal data', security: [['bearerAuth' => []]], tags: ['GDPR'], responses: [
        new OA\Response(response: 200, description: 'Personal profile and consent history.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/GdprDataAccess')], type: 'object')),
        new OA\Response(response: 401, description: 'Unauthenticated.'),
    ])]
    public function gdprSelfAccess(): void
    {
    }

    #[OA\Post(path: '/me/privacy/exports', summary: 'Queue an export of own personal data', security: [['bearerAuth' => []]], tags: ['GDPR'], responses: [
        new OA\Response(response: 202, description: 'Export queued.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/GdprExport')], type: 'object')),
        new OA\Response(response: 401, description: 'Unauthenticated.'),
    ])]
    public function gdprSelfExport(): void
    {
    }

    #[OA\Patch(path: '/me/privacy/rectification', summary: 'Rectify own profile data', security: [['bearerAuth' => []]], tags: ['GDPR'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/GdprRectificationRequest')), responses: [
        new OA\Response(response: 200, description: 'Profile rectified.'),
        new OA\Response(response: 422, description: 'Validation failed.'),
    ])]
    public function gdprSelfRectification(): void
    {
    }

    #[OA\Post(path: '/me/privacy/consents', summary: 'Append an own consent or withdrawal event', security: [['bearerAuth' => []]], tags: ['GDPR'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ConsentRecordRequest')), responses: [
        new OA\Response(response: 201, description: 'Consent event appended.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/ConsentRecord')], type: 'object')),
        new OA\Response(response: 422, description: 'Validation failed.'),
    ])]
    public function gdprSelfConsent(): void
    {
    }

    #[OA\Post(path: '/me/privacy/erasure-requests', summary: 'Request erasure of own data', security: [['bearerAuth' => []]], tags: ['GDPR'], responses: [
        new OA\Response(response: 202, description: 'Erasure request recorded.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/GdprRequest')], type: 'object')),
    ])]
    public function gdprSelfErasure(): void
    {
    }

    #[OA\Get(path: '/privacy/exports/{export}', summary: 'Get export status and temporary download URL', security: [['bearerAuth' => []]], tags: ['GDPR'], parameters: [new OA\PathParameter(name: 'export', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))], responses: [
        new OA\Response(response: 200, description: 'Export status.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/GdprExport')], type: 'object')),
        new OA\Response(response: 403, description: 'Not the data subject and missing gdpr.export right.'),
        new OA\Response(response: 404, description: 'Export not found in current tenant.'),
    ])]
    public function gdprExportStatus(): void
    {
    }

    #[OA\Get(path: '/privacy/exports/{export}/download', summary: 'Download an unexpired private export', security: [['bearerAuth' => []]], tags: ['GDPR'], parameters: [new OA\PathParameter(name: 'export', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))], responses: [
        new OA\Response(response: 200, description: 'JSON export file.', content: new OA\MediaType(mediaType: 'application/json')),
        new OA\Response(response: 403, description: 'Invalid/expired signature or insufficient access.'),
        new OA\Response(response: 404, description: 'Export not found in current tenant.'),
    ])]
    public function gdprExportDownload(): void
    {
    }

    #[OA\Get(path: '/users/{user}/privacy/data', summary: 'Administratively access a user data set (requires gdpr.export)', security: [['bearerAuth' => []]], tags: ['GDPR'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Tenant user data.'), new OA\Response(response: 403, description: 'Missing gdpr.export right.'), new OA\Response(response: 404, description: 'User not found in current tenant.')])]
    public function gdprAdminAccess(): void
    {
    }

    #[OA\Post(path: '/users/{user}/privacy/exports', summary: 'Administratively queue a user export (requires gdpr.export)', security: [['bearerAuth' => []]], tags: ['GDPR'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 202, description: 'Export queued.'), new OA\Response(response: 403, description: 'Missing gdpr.export right.'), new OA\Response(response: 404, description: 'User not found in current tenant.')])]
    public function gdprAdminExport(): void
    {
    }

    #[OA\Patch(path: '/users/{user}/privacy/rectification', summary: 'Administratively rectify user data (requires gdpr.process)', security: [['bearerAuth' => []]], tags: ['GDPR'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/GdprRectificationRequest')), responses: [new OA\Response(response: 200, description: 'Profile rectified.'), new OA\Response(response: 403, description: 'Missing gdpr.process right.'), new OA\Response(response: 422, description: 'Validation failed.')])]
    public function gdprAdminRectification(): void
    {
    }

    #[OA\Post(path: '/users/{user}/privacy/consents', summary: 'Administratively append a consent event (requires gdpr.process)', security: [['bearerAuth' => []]], tags: ['GDPR'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ConsentRecordRequest')), responses: [new OA\Response(response: 201, description: 'Consent event appended.'), new OA\Response(response: 403, description: 'Missing gdpr.process right.'), new OA\Response(response: 422, description: 'Validation failed.')])]
    public function gdprAdminConsent(): void
    {
    }

    #[OA\Post(path: '/users/{user}/privacy/erasure-requests', summary: 'Administratively create an erasure request (requires gdpr.process)', security: [['bearerAuth' => []]], tags: ['GDPR'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 202, description: 'Erasure request recorded.'), new OA\Response(response: 403, description: 'Missing gdpr.process right.'), new OA\Response(response: 404, description: 'User not found in current tenant.')])]
    public function gdprAdminErasure(): void
    {
    }

    #[OA\Post(path: '/privacy/requests/{gdprRequest}/process', summary: 'Process a pending erasure request (requires gdpr.process)', security: [['bearerAuth' => []]], tags: ['GDPR'], parameters: [new OA\PathParameter(name: 'gdprRequest', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))], responses: [new OA\Response(response: 200, description: 'Retention workflow completed.'), new OA\Response(response: 403, description: 'Missing gdpr.process right.'), new OA\Response(response: 404, description: 'Request not found in current tenant.'), new OA\Response(response: 422, description: 'Request cannot be processed or actor is the subject.')])]
    public function gdprProcessErasure(): void
    {
    }

    #[OA\Get(path: '/users/{user}/documents', summary: 'List member documents', security: [['bearerAuth' => []]], tags: ['User Documents'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Paginated user documents.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/UserDocument'))])), new OA\Response(response: 403, description: 'Missing user-documents.view right.'), new OA\Response(response: 404, description: 'User not found in current tenant.')])]
    public function userDocumentsIndex(): void
    {
    }

    #[OA\Post(path: '/users/{user}/documents', summary: 'Upload a member document', security: [['bearerAuth' => []]], tags: ['User Documents'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(ref: '#/components/schemas/UserDocumentUploadRequest'))), responses: [new OA\Response(response: 200, description: 'Document uploaded.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/UserDocument')])), new OA\Response(response: 403, description: 'Missing user-documents.upload right.'), new OA\Response(response: 422, description: 'Validation or antivirus scan failed.')])]
    public function userDocumentsStore(): void
    {
    }

    #[OA\Post(path: '/users/{user}/documents/{document}/replace', summary: 'Replace a member document with a new version', security: [['bearerAuth' => []]], tags: ['User Documents'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')), new OA\PathParameter(name: 'document', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(ref: '#/components/schemas/UserDocumentUploadRequest'))), responses: [new OA\Response(response: 200, description: 'Replacement document uploaded.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/UserDocument')])), new OA\Response(response: 403, description: 'Missing user-documents.upload right.'), new OA\Response(response: 404, description: 'User or document not found in current tenant.'), new OA\Response(response: 422, description: 'Validation or antivirus scan failed.')])]
    public function userDocumentsReplace(): void
    {
    }

    #[OA\Post(path: '/users/{user}/documents/{document}/download-url', summary: 'Create a temporary signed document download URL', security: [['bearerAuth' => []]], tags: ['User Documents'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')), new OA\PathParameter(name: 'document', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Temporary signed URL.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/UserDocumentDownloadUrl')])), new OA\Response(response: 403, description: 'Missing user-documents.view right.'), new OA\Response(response: 404, description: 'User or document not found in current tenant.')])]
    public function userDocumentsDownloadUrl(): void
    {
    }

    #[OA\Get(path: '/users/{user}/documents/{document}/download', summary: 'Download a member document through a signed URL', security: [['bearerAuth' => []]], tags: ['User Documents'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')), new OA\PathParameter(name: 'document', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Private document file.', content: new OA\MediaType(mediaType: 'application/octet-stream', schema: new OA\Schema(type: 'string', format: 'binary'))), new OA\Response(response: 403, description: 'Invalid signature or missing user-documents.view right.'), new OA\Response(response: 404, description: 'User or document not found in current tenant.')])]
    public function userDocumentsDownload(): void
    {
    }

    #[OA\Delete(path: '/users/{user}/documents/{document}', summary: 'Delete a member document', security: [['bearerAuth' => []]], tags: ['User Documents'], parameters: [new OA\PathParameter(name: 'user', required: true, schema: new OA\Schema(type: 'integer')), new OA\PathParameter(name: 'document', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 204, description: 'Document deleted.'), new OA\Response(response: 403, description: 'Missing user-documents.delete right.'), new OA\Response(response: 404, description: 'User or document not found in current tenant.')])]
    public function userDocumentsDestroy(): void
    {
    }
}
