<?php

namespace App\Dashboard\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DashboardResponse',
    properties: [
        new OA\Property(property: 'filters', properties: [
            new OA\Property(property: 'from', type: 'string', format: 'date', example: '2026-07-01'),
            new OA\Property(property: 'to', type: 'string', format: 'date', example: '2026-07-31'),
            new OA\Property(property: 'group_by', type: 'string', enum: ['day', 'month'], example: 'month'),
        ], type: 'object'),
        new OA\Property(property: 'stats', properties: [
            new OA\Property(property: 'active_members', type: 'integer', example: 120),
            new OA\Property(property: 'flagged_services', type: 'integer', example: 8),
            new OA\Property(property: 'total_revenue', type: 'number', format: 'float', example: 14250.5),
            new OA\Property(property: 'active_locations', type: 'integer', example: 3),
        ], type: 'object'),
        new OA\Property(property: 'revenue_by_period', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'period', type: 'string', example: '2026-07'),
            new OA\Property(property: 'revenue', type: 'number', format: 'float', example: 4300),
        ], type: 'object')),
        new OA\Property(property: 'member_status', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'status', type: 'string', example: 'active'),
            new OA\Property(property: 'count', type: 'integer', example: 80),
        ], type: 'object')),
        new OA\Property(property: 'activity', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'period', type: 'string', example: '2026-07'),
            new OA\Property(property: 'active', type: 'integer', example: 14),
            new OA\Property(property: 'messages', type: 'integer', example: 4),
        ], type: 'object')),
        new OA\Property(property: 'automations', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'key', type: 'string', example: 'service_expiry_notifications'),
            new OA\Property(property: 'label', type: 'string', example: 'Service expiry notifications'),
            new OA\Property(property: 'enabled', type: 'boolean', example: true),
            new OA\Property(property: 'helper', type: 'string', example: 'Scheduled daily notification workflow.'),
            new OA\Property(property: 'count', type: 'integer', nullable: true, example: 8),
        ], type: 'object')),
    ],
    type: 'object',
)]
class Schemas
{
}
