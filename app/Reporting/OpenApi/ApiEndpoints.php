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
                        new OA\Property(
                            property: 'receivables',
                            description: 'Obligations = service assignments + billable paid-event participations (registered/attended, requires_payment, payment_amount > 0, occurrence not cancelled). paid = confirmed payments on both targets. service_id/service_type filters exclude events.',
                            properties: [
                                new OA\Property(property: 'invoiced', type: 'number', format: 'float', example: 250),
                                new OA\Property(property: 'paid', type: 'number', format: 'float', example: 130),
                                new OA\Property(property: 'outstanding', type: 'number', format: 'float', example: 120),
                                new OA\Property(property: 'by_type', type: 'object', description: 'Same three amounts split into `service` and `event` keys.', example: ['service' => ['invoiced' => 50, 'paid' => 0, 'outstanding' => 50], 'event' => ['invoiced' => 200, 'paid' => 130, 'outstanding' => 70]]),
                            ],
                            type: 'object',
                        ),
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
        summary: 'List service and event payment obligations and their aging',
        description: 'Returns one organization-scoped row per service assignment (obligation_type=service, date = assignment creation) and per billable paid-event participation (obligation_type=event, obligation_id = event_occurrence_user.id, invoiced_at = occurrence start, service_id null, service_name = "Eveniment <title> - dd.mm.yyyy", event_id/event_title/occurrence_id set). Each row has invoiced/paid/balance, days_overdue and aging_bucket (1-7, 8-30, 31-60, 60+ or null). service_id/service_type filters exclude event rows; from/to apply to the obligation date.',
        security: [['bearerAuth' => []]],
        tags: ['Reporting'],
        parameters: [
            new OA\QueryParameter(name: 'from', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'to', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'location_id', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'service_id', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'member_id', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'service_type', required: false, schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'segment_id', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Organization-scoped receivable rows.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/FinancialReceivableRow')),
            ], type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Missing reports.view right.'),
            new OA\Response(response: 422, description: 'Invalid report filters.'),
            new OA\Response(response: 429, description: 'Expensive-endpoint rate limit exceeded.'),
        ],
    )]
    public function financialReceivables(): void
    {
    }

    #[OA\Get(
        path: '/reports/financial-documents',
        summary: 'List issued financial documents',
        description: 'Organization-scoped list of service invoices (type=invoice), service payment notes (payment_note), event participation invoices (event_invoice, id = event_occurrence_user.id) and receipts (receipt, id = payment id), sorted by date desc. from/to apply to assignment creation (invoice/payment_note), invoiced_at (event_invoice) or paid_at (receipt).',
        security: [['bearerAuth' => []]],
        tags: ['Reporting'],
        parameters: [
            new OA\QueryParameter(name: 'from', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'to', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Document rows.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/FinancialDocumentRow')),
            ], type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Missing reports.view right or a cross-organization filter was requested.'),
            new OA\Response(response: 429, description: 'Expensive-endpoint rate limit exceeded.'),
        ],
    )]
    public function financialDocuments(): void
    {
    }

    #[OA\Get(
        path: '/reports/financial-documents/{type}/{id}/download/{format}',
        summary: 'Download one financial document',
        description: 'Downloads a document listed by /reports/financial-documents. format (optional, default pdf) = xml is available for invoice and event_invoice (UBL e-Factura).',
        security: [['bearerAuth' => []]],
        tags: ['Reporting'],
        parameters: [
            new OA\PathParameter(name: 'type', required: true, schema: new OA\Schema(type: 'string', enum: ['invoice', 'payment_note', 'event_invoice', 'receipt'])),
            new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\PathParameter(name: 'format', required: true, schema: new OA\Schema(type: 'string', enum: ['pdf', 'xml'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'PDF or XML document.'),
            new OA\Response(response: 401, description: 'Unauthenticated.'),
            new OA\Response(response: 403, description: 'Missing reports.export right.'),
            new OA\Response(response: 404, description: 'Unknown type, document of another organization, or document number not issued.'),
        ],
    )]
    public function financialDocumentDownload(): void
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
    description: 'Per-service or per-service-type subscription and revenue metrics, followed by paid-event rows (obligation_type=event; one per event for group_by=service, one service_type=event row for group_by=service_type). Service identity is omitted for service_type grouping. subscriptions = participations for event rows.',
    properties: [
        new OA\Property(property: 'obligation_type', type: 'string', enum: ['service', 'event']),
        new OA\Property(property: 'service_id', type: 'integer', example: 12, description: 'Absent for event rows and service_type grouping.'),
        new OA\Property(property: 'service_name', type: 'string', example: 'Annual membership', description: 'For event rows: "Eveniment <title>".'),
        new OA\Property(property: 'event_id', type: 'integer', example: 4, description: 'Only event rows with group_by=service.'),
        new OA\Property(property: 'event_title', type: 'string', example: 'Seminar', description: 'Only event rows with group_by=service.'),
        new OA\Property(property: 'service_type', type: 'string', example: 'membership', description: 'Service type, or "event" for paid-event obligations.'),
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

#[OA\Schema(
    schema: 'FinancialReceivableRow',
    properties: [
        new OA\Property(property: 'obligation_id', type: 'integer', description: 'service_user.id (service) or event_occurrence_user.id (event).'),
        new OA\Property(property: 'obligation_type', type: 'string', enum: ['service', 'event']),
        new OA\Property(property: 'invoiced_at', type: 'string', format: 'date-time', description: 'Assignment creation (service) or occurrence start (event).'),
        new OA\Property(property: 'invoice_number', type: 'string', nullable: true),
        new OA\Property(property: 'service_id', type: 'integer', nullable: true),
        new OA\Property(property: 'service_name', type: 'string', nullable: true),
        new OA\Property(property: 'event_id', type: 'integer', nullable: true),
        new OA\Property(property: 'event_title', type: 'string', nullable: true),
        new OA\Property(property: 'occurrence_id', type: 'integer', nullable: true),
        new OA\Property(property: 'member_id', type: 'integer'),
        new OA\Property(property: 'member_name', type: 'string'),
        new OA\Property(property: 'currency', type: 'string', nullable: true),
        new OA\Property(property: 'invoiced_amount', type: 'number', format: 'float'),
        new OA\Property(property: 'paid_amount', type: 'number', format: 'float'),
        new OA\Property(property: 'balance', type: 'number', format: 'float'),
        new OA\Property(property: 'days_overdue', type: 'integer'),
        new OA\Property(property: 'aging_bucket', type: 'string', nullable: true, enum: ['1-7', '8-30', '31-60', '60+']),
    ],
    type: 'object',
)]
class FinancialReceivableRowSchema
{
}

#[OA\Schema(
    schema: 'FinancialDocumentRow',
    properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'service_user.id (invoice, payment_note), event_occurrence_user.id (event_invoice), payments.id (receipt).'),
        new OA\Property(property: 'type', type: 'string', enum: ['invoice', 'payment_note', 'event_invoice', 'receipt']),
        new OA\Property(property: 'type_label', type: 'string', example: 'Factura'),
        new OA\Property(property: 'number', type: 'string', example: 'INV000001'),
        new OA\Property(property: 'date', type: 'string', format: 'date'),
        new OA\Property(property: 'member', type: 'string'),
        new OA\Property(property: 'description', type: 'string', example: 'Eveniment Seminar - 20.07.2026', description: 'Service name, or "Eveniment <title> - dd.mm.yyyy"; receipts describe the paid object (fallback "Plata #id").'),
        new OA\Property(property: 'source', type: 'string', nullable: true, enum: ['service', 'event'], description: 'Paid/invoiced object kind; null for receipts without a resolvable target.'),
        new OA\Property(property: 'event_id', type: 'integer', description: 'Only on event_invoice rows.'),
        new OA\Property(property: 'amount', type: 'number', format: 'float'),
        new OA\Property(property: 'currency', type: 'string', nullable: true),
        new OA\Property(property: 'filename', type: 'string'),
        new OA\Property(property: 'xml_filename', type: 'string', nullable: true, description: 'Present on invoice (null for payment_note) and event_invoice rows.'),
    ],
    type: 'object',
)]
class FinancialDocumentRowSchema
{
}
