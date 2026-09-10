<?php

namespace App\Articles\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Article',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'title', type: 'string', example: 'Quarterly Operations Update'),
        new OA\Property(property: 'description', type: 'string', example: 'Summary of operational changes for the quarter.'),
        new OA\Property(property: 'publish_at', description: 'Date and time from which the announcement can be published.', type: 'string', format: 'date-time', nullable: true, example: '2026-08-10T08:00:00Z'),
        new OA\Property(property: 'expires_at', description: 'Date and time after which the announcement is no longer visible.', type: 'string', format: 'date-time', nullable: true, example: '2026-08-31T23:59:59Z'),
        new OA\Property(property: 'priority', description: 'Higher values are displayed first in the user feed.', type: 'integer', minimum: 0, example: 10),
        new OA\Property(property: 'status', description: 'Current publication lifecycle state.', type: 'string', enum: ['draft', 'scheduled', 'published', 'expired'], example: 'published'),
        new OA\Property(property: 'audience_segment', description: 'Users eligible to receive the announcement. The groups and locations segments use the corresponding ID arrays.', type: 'string', enum: ['all_users', 'active_subscribers', 'expired_users', 'groups', 'locations'], example: 'groups'),
        new OA\Property(property: 'segment_id', description: 'Optional dynamic segment from the same organization. When present, visibility is evaluated from the current segment membership.', type: 'integer', nullable: true, example: 3),
        new OA\Property(property: 'created_by', type: 'integer', example: 1),
        new OA\Property(property: 'organization_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'author', ref: '#/components/schemas/User'),
        new OA\Property(
            property: 'groups',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Group'),
        ),
        new OA\Property(
            property: 'locations',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Location'),
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'delivered_at', description: 'Delivery time for the authenticated user; only present in feed responses.', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'viewed_at', description: 'View time for the authenticated user; only present in feed responses.', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StoreArticleRequest',
    required: ['title', 'description'],
    properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Quarterly Operations Update'),
        new OA\Property(property: 'description', type: 'string', example: 'Summary of operational changes for the quarter.'),
        new OA\Property(property: 'publish_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'priority', type: 'integer', minimum: 0),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'scheduled', 'published', 'expired']),
        new OA\Property(property: 'audience_segment', description: 'Use groups or locations together with the corresponding ID array.', type: 'string', enum: ['all_users', 'active_subscribers', 'expired_users', 'groups', 'locations'], example: 'all_users'),
        new OA\Property(property: 'segment_id', description: 'Dynamic segment ID from the authenticated organization.', type: 'integer', nullable: true, example: 3),
        new OA\Property(
            property: 'groups',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
        new OA\Property(
            property: 'locations',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 3],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateArticleRequest',
    properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Quarterly Operations Update'),
        new OA\Property(property: 'description', type: 'string', example: 'Summary of operational changes for the quarter.'),
        new OA\Property(property: 'publish_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'priority', type: 'integer', minimum: 0),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'scheduled', 'published', 'expired']),
        new OA\Property(property: 'audience_segment', description: 'Use groups or locations together with the corresponding ID array.', type: 'string', enum: ['all_users', 'active_subscribers', 'expired_users', 'groups', 'locations']),
        new OA\Property(property: 'segment_id', description: 'Dynamic segment ID from the authenticated organization; null removes dynamic targeting.', type: 'integer', nullable: true, example: 3),
        new OA\Property(
            property: 'groups',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
        new OA\Property(
            property: 'locations',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 3],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ArticleFeedResponse',
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Article')),
        new OA\Property(
            property: 'links',
            properties: [
                new OA\Property(property: 'first', type: 'string', nullable: true),
                new OA\Property(property: 'last', type: 'string', nullable: true),
                new OA\Property(property: 'prev', type: 'string', nullable: true),
                new OA\Property(property: 'next', type: 'string', nullable: true),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'meta',
            properties: [
                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
                new OA\Property(property: 'last_page', type: 'integer', example: 1),
                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                new OA\Property(property: 'to', type: 'integer', nullable: true, example: 4),
                new OA\Property(property: 'total', type: 'integer', example: 4),
            ],
            type: 'object',
        ),
    ],
    type: 'object',
)]
class Schemas
{
}
