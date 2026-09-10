<?php

namespace App\Dashboard\OpenApi;

use OpenApi\Attributes as OA;

class ApiEndpoints
{
    #[OA\Get(
        path: '/dashboard',
        summary: 'Get dashboard aggregates',
        description: 'Returns tenant-safe dashboard KPIs, charts, activity, and automation indicators for the authenticated organization.',
        tags: ['Dashboard'],
        parameters: [
            new OA\QueryParameter(name: 'from', required: false, schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-07-01'),
            new OA\QueryParameter(name: 'to', required: false, schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-07-31'),
            new OA\QueryParameter(name: 'group_by', required: false, schema: new OA\Schema(type: 'string', enum: ['day', 'month'], default: 'month')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dashboard aggregates.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/DashboardResponse'),
                ], type: 'object'),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Missing dashboard.view, reports.view, or reports.manage right.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function show(): void
    {
    }
}
