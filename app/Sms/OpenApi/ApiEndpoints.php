<?php

namespace App\Sms\OpenApi;

use OpenApi\Attributes as OA;

class ApiEndpoints
{
    #[OA\Get(
        path: '/sms-messages',
        summary: 'List SMS messages',
        security: [['bearerAuth' => []]],
        tags: ['SMS'],
        parameters: [
            new OA\QueryParameter(name: 'search', required: false, description: 'Search by destination, message, user fields, or service name.', schema: new OA\Schema(type: 'string'), example: 'Gold'),
            new OA\QueryParameter(name: 'status', required: false, schema: new OA\Schema(type: 'string'), example: 'sent'),
            new OA\QueryParameter(name: 'type', required: false, schema: new OA\Schema(type: 'string'), example: 'service_expiring'),
            new OA\QueryParameter(name: 'user_id', required: false, schema: new OA\Schema(type: 'integer'), example: 35),
            new OA\QueryParameter(name: 'service_id', required: false, schema: new OA\Schema(type: 'integer'), example: 2),
            new OA\QueryParameter(name: 'destination', required: false, schema: new OA\Schema(type: 'string'), example: '0722'),
            new OA\QueryParameter(name: 'sent_from', required: false, schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
            new OA\QueryParameter(name: 'sent_to', required: false, schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated SMS message list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/SmsMessage'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing sms.view or services.manage right.'),
        ],
    )]
    public function index(): void
    {
    }
}