<?php

namespace App\Reporting\OpenApi;

use OpenApi\Attributes as OA;

class ApiEndpoints
{
    #[OA\Get(
        path: '/reports/event-participation',
        summary: 'Aggregate event participation and occupancy',
        description: 'Groups organization event occurrences by category, location, day and time interval. Occupancy is based on active registrations (registered and attended); actual participation counts attended records.',
        security: [['bearerAuth' => []]],
        tags: ['Reporting'],
        parameters: [
            new OA\QueryParameter(name: 'from', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'to', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'category_id', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'location', required: false, schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'time_from', required: false, schema: new OA\Schema(type: 'string', format: 'time', example: '09:00')),
            new OA\QueryParameter(name: 'time_to', required: false, schema: new OA\Schema(type: 'string', format: 'time', example: '17:00')),
            new OA\QueryParameter(name: 'underutilized_below', required: false, schema: new OA\Schema(type: 'number', minimum: 0, maximum: 100, default: 50)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Aggregated capacity, registrations, attendances, occupancy percentage and utilization status.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Missing reports.view right or a cross-organization filter was requested.'),
            new OA\Response(response: 422, description: 'Invalid report filters.'),
        ],
    )]
    public function eventParticipationReport(): void
    {
    }

    #[OA\Get(
        path: '/reports/financial',
        summary: 'Aggregate the financial report',
        description: 'Runs an organization-scoped financial aggregation. This expensive operation is rate limited by client IP, authenticated organization and user identity.',
        security: [['bearerAuth' => []]],
        tags: ['Reporting'],
        parameters: [
            new OA\QueryParameter(name: 'from', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'to', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'location_id', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'admin_id', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'payment_type_id', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'status', required: false, schema: new OA\Schema(type: 'string', enum: ['initiated', 'pending', 'confirmed', 'failed', 'refunded', 'cancelled'])),
            new OA\QueryParameter(name: 'service_id', description: 'Restrict the report to one service in the authenticated organization.', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'service_type', required: false, schema: new OA\Schema(type: 'string', enum: ['membership', 'access_pass'])),
            new OA\QueryParameter(name: 'segment_id', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'group_by', description: 'Date groupings populate revenue_by_period; service groupings populate revenue_by_service or revenue_by_service_type.', required: false, schema: new OA\Schema(type: 'string', enum: ['day', 'month', 'service', 'service_type'], default: 'month')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization-scoped financial totals and the requested series.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'revenue_by_period', type: 'array', items: new OA\Items(properties: [
                            new OA\Property(property: 'period', type: 'string', example: '2026-08'),
                            new OA\Property(property: 'total', type: 'number', format: 'float', example: 1250),
                        ], type: 'object')),
                        new OA\Property(property: 'revenue_by_service', type: 'array', items: new OA\Items(ref: '#/components/schemas/FinancialServiceAggregation')),
                        new OA\Property(property: 'revenue_by_service_type', type: 'array', items: new OA\Items(ref: '#/components/schemas/FinancialServiceAggregation')),
                    ], type: 'object'),
                ], type: 'object'),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Missing reports.view right or a cross-organization filter was requested.'),
            new OA\Response(response: 422, description: 'Invalid report filters.'),
            new OA\Response(response: 429, description: 'Expensive-endpoint rate limit exceeded.'),
        ],
    )]
    public function financialReport(): void
    {
    }

    #[OA\Get(
        path: '/reports/financial/receivables',
        summary: 'List service payment obligations and their aging',
        description: 'Returns one organization-scoped row per service assignment, including invoiced, paid and outstanding amounts, overdue days and aging bucket.',
        security: [['bearerAuth' => []]],
        tags: ['Reporting'],
        parameters: [
            new OA\QueryParameter(name: 'from', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'to', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'location_id', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'service_id', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'member_id', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Organization-scoped receivable rows.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Missing reports.view right.'),
            new OA\Response(response: 422, description: 'Invalid report filters.'),
            new OA\Response(response: 429, description: 'Expensive-endpoint rate limit exceeded.'),
        ],
    )]
    public function financialReceivables(): void
    {
    }

    #[OA\Post(
        path: '/reports/financial/exports',
        summary: 'Queue a financial report export',
        description: 'Queues a CSV or XLSX export. Export creation is protected by the expensive-endpoint rate limit.',
        security: [['bearerAuth' => []]],
        tags: ['Reporting'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['format'],
                properties: [
                    new OA\Property(property: 'format', type: 'string', enum: ['csv', 'xlsx']),
                    new OA\Property(property: 'service_id', type: 'integer'),
                    new OA\Property(property: 'service_type', type: 'string', enum: ['membership', 'access_pass']),
                    new OA\Property(property: 'group_by', type: 'string', enum: ['day', 'month', 'service', 'service_type']),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(response: 202, description: 'Export queued.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Missing reports.export right.'),
            new OA\Response(response: 422, description: 'Invalid export format or filters.'),
            new OA\Response(response: 429, description: 'Expensive-endpoint rate limit exceeded.'),
        ],
    )]
    public function financialExport(): void
    {
    }

    #[OA\Get(
        path: '/segments/{segment}/members',
        summary: 'Evaluate members of a saved segment',
        description: 'Returns organization-scoped matching members. Segment evaluation is protected by the expensive-endpoint rate limit.',
        security: [['bearerAuth' => []]],
        tags: ['Reporting'],
        parameters: [
            new OA\PathParameter(name: 'segment', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated matching users.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Missing segments.view or segments.manage right.'),
            new OA\Response(response: 404, description: 'Segment not found in the authenticated organization.'),
            new OA\Response(response: 429, description: 'Expensive-endpoint rate limit exceeded.'),
        ],
    )]
    public function segmentMembers(): void
    {
    }
}

#[OA\Schema(
    schema: 'FinancialServiceAggregation',
    description: 'Per-service or per-service-type subscription and revenue metrics. Service identity is omitted for service_type grouping.',
    properties: [
        new OA\Property(property: 'service_id', type: 'integer', example: 12),
        new OA\Property(property: 'service_name', type: 'string', example: 'Annual membership'),
        new OA\Property(property: 'service_type', type: 'string', enum: ['membership', 'access_pass']),
        new OA\Property(property: 'subscriptions', type: 'integer', example: 25),
        new OA\Property(property: 'invoiced', type: 'number', format: 'float', example: 3000),
        new OA\Property(property: 'confirmed', type: 'number', format: 'float', example: 2400),
        new OA\Property(property: 'refunded', type: 'number', format: 'float', example: 120),
        new OA\Property(property: 'outstanding', type: 'number', format: 'float', example: 720),
        new OA\Property(property: 'average_revenue_per_member', type: 'number', format: 'float', example: 91.2),
    ],
    type: 'object',
)]
class FinancialServiceAggregationSchema
{
}
