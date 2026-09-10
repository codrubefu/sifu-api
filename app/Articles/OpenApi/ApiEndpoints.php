<?php

namespace App\Articles\OpenApi;

use OpenApi\Attributes as OA;

class ApiEndpoints
{
    #[OA\Get(
        path: '/articles-feed',
        summary: 'List publishable announcements targeted to the authenticated user',
        security: [['bearerAuth' => []]],
        tags: ['Articles'],
        parameters: [
            new OA\QueryParameter(
                name: 'per_page',
                description: 'Number of announcements per page.',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 15, minimum: 1),
                example: 15,
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Priority-ordered targeted announcements. Returned announcements are marked as delivered for the authenticated user.',
                content: new OA\JsonContent(ref: '#/components/schemas/ArticleFeedResponse'),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
        ],
    )]
    public function feed(): void
    {
    }

    #[OA\Post(
        path: '/articles/{article}/view',
        summary: 'Mark a targeted announcement as viewed',
        security: [['bearerAuth' => []]],
        tags: ['Articles'],
        parameters: [new OA\PathParameter(name: 'article', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'View timestamp recorded.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Article')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 404, description: 'Announcement is missing or not visible to this user.'),
        ],
    )]
    public function markViewed(): void
    {
    }

    #[OA\Get(
        path: '/articles',
        summary: 'List articles',
        security: [['bearerAuth' => []]],
        tags: ['Articles'],
        parameters: [
            new OA\QueryParameter(name: 'search', required: false, schema: new OA\Schema(type: 'string'), example: 'operations'),
            new OA\QueryParameter(name: 'group_id', required: false, schema: new OA\Schema(type: 'integer'), example: 1),
            new OA\QueryParameter(name: 'location_id', required: false, schema: new OA\Schema(type: 'integer'), example: 2),
            new OA\QueryParameter(name: 'user_id', required: false, schema: new OA\Schema(type: 'integer'), example: 1),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
            new OA\QueryParameter(name: 'with_trashed', required: false, schema: new OA\Schema(type: 'boolean'), example: false),
            new OA\QueryParameter(name: 'only_trashed', required: false, schema: new OA\Schema(type: 'boolean'), example: false),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated article list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Article'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing articles.view or articles.manage right.'),
        ],
    )]
    public function index(): void
    {
    }

    #[OA\Post(
        path: '/articles',
        summary: 'Create an article',
        security: [['bearerAuth' => []]],
        tags: ['Articles'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreArticleRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Article created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Article'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing articles.create or articles.manage right.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function store(): void
    {
    }

    #[OA\Get(
        path: '/articles/{article}',
        summary: 'Show an article',
        security: [['bearerAuth' => []]],
        tags: ['Articles'],
        parameters: [
            new OA\PathParameter(name: 'article', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Article details.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Article'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing articles.view or articles.manage right.'),
            new OA\Response(response: 404, description: 'Article not found.'),
        ],
    )]
    public function show(): void
    {
    }

    #[OA\Patch(
        path: '/articles/{article}',
        summary: 'Update an article',
        security: [['bearerAuth' => []]],
        tags: ['Articles'],
        parameters: [
            new OA\PathParameter(name: 'article', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateArticleRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Article updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Article'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing articles.update or articles.manage right.'),
            new OA\Response(response: 404, description: 'Article not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function update(): void
    {
    }

    #[OA\Put(
        path: '/articles/{article}',
        summary: 'Replace an article',
        security: [['bearerAuth' => []]],
        tags: ['Articles'],
        parameters: [
            new OA\PathParameter(name: 'article', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateArticleRequest'),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Article updated.'),
            new OA\Response(response: 403, description: 'Missing articles.update or articles.manage right.'),
            new OA\Response(response: 404, description: 'Article not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function replace(): void
    {
    }

    #[OA\Delete(
        path: '/articles/{article}',
        summary: 'Delete an article',
        security: [['bearerAuth' => []]],
        tags: ['Articles'],
        parameters: [
            new OA\PathParameter(name: 'article', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Article deleted.'),
            new OA\Response(response: 403, description: 'Missing articles.delete or articles.manage right.'),
            new OA\Response(response: 404, description: 'Article not found.'),
        ],
    )]
    public function destroy(): void
    {
    }
}
