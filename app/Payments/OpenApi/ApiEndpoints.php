<?php

namespace App\Payments\OpenApi;

use OpenApi\Attributes as OA;

class ApiEndpoints
{
    #[OA\Get(
        path: '/payments',
        summary: 'List payments',
        description: 'Returns only payments belonging to the authenticated organization, including their lifecycle and traceability fields.',
        security: [['bearerAuth' => []]],
        tags: ['Payment'],
        parameters: [
            new OA\QueryParameter(name: 'per_page', required: false, schema: new OA\Schema(type: 'integer'), example: 15),
            new OA\QueryParameter(name: 'page', required: false, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated payment list.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Payment'),
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing payments.view or payments.manage right.'),
        ],
    )]
    public function index(): void
    {
    }

    #[OA\Post(
        path: '/payments',
        summary: 'Create a payment',
        description: 'Creates a payment linked to a payable object from the authenticated organization. Organization, branch and operator are inferred from authentication. Cash and card are confirmed immediately; for service_user payments, receipt issuance and lifecycle activation are committed atomically. Bank payments start as initiated.',
        security: [['bearerAuth' => []]],
        tags: ['Payment'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StorePaymentRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Payment created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Payment'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing payments.create or payments.manage right.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function store(): void
    {
    }

    #[OA\Patch(
        path: '/payments/{payment}/attach-model',
        summary: 'Attach or update payment model metadata',
        description: 'Reassigns the payment to one of the supported payable model types: service_user or event_occurrence_user.',
        security: [['bearerAuth' => []]],
        tags: ['Payment'],
        parameters: [
            new OA\PathParameter(name: 'payment', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AttachPaymentModelRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment model metadata updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Payment'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 403, description: 'Missing payments.update or payments.manage right.'),
            new OA\Response(response: 404, description: 'Payment not found.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function attachModel(): void
    {
    }

    #[OA\Post(
        path: '/payments/callback',
        summary: 'Process a payment provider callback',
        description: 'Processes an idempotent card or bank callback. A confirmed service payment activates only the exact service_user assignment in the same organization; paid_at alone is not confirmation. Receipt issuance and activation are atomic, while activation notification and audit are emitted after commit. Duplicate confirmed callbacks do not repeat activation side effects. The X-Payment-Signature value must be the lowercase hexadecimal HMAC-SHA256 of the exact raw request body, calculated with PAYMENT_CALLBACK_SECRET.',
        tags: ['Payment'],
        parameters: [
            new OA\HeaderParameter(
                name: 'X-Payment-Signature',
                required: true,
                description: 'Hexadecimal HMAC-SHA256 signature of the raw JSON request body.',
                schema: new OA\Schema(type: 'string', example: 'f77d7a45a507d93688c8c6ae93f9c8f74e0acff810f2e98a8cc63fcb23c81a48'),
            ),
            new OA\HeaderParameter(
                name: 'X-Organization-Id',
                required: true,
                description: 'Declared organization identifier used as part of the callback rate-limit key.',
                schema: new OA\Schema(type: 'integer', example: 1),
            ),
            new OA\HeaderParameter(
                name: 'X-Provider-Id',
                required: false,
                description: 'Stable provider identity used in the callback rate-limit key. When omitted, external_reference is used.',
                schema: new OA\Schema(type: 'string', example: 'netopia-production'),
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PaymentCallbackRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Callback accepted. Repeated callbacks return the existing payment without repeating side effects.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Payment')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Missing or invalid provider signature.'),
            new OA\Response(response: 404, description: 'External payment reference not found.'),
            new OA\Response(response: 422, description: 'Invalid callback payload or status.'),
            new OA\Response(response: 429, description: 'Callback rate limit exceeded.'),
        ],
    )]
    public function callback(): void
    {
    }

    #[OA\Get(
        path: '/payments/{payment}/receipt',
        summary: 'Download a payment receipt',
        description: 'Downloads the receipt for a confirmed payment belonging to the authenticated organization.',
        security: [['bearerAuth' => []]],
        tags: ['Payment'],
        parameters: [
            new OA\PathParameter(name: 'payment', required: true, schema: new OA\Schema(type: 'integer'), example: 8),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Receipt PDF file.',
                content: new OA\MediaType(
                    mediaType: 'application/pdf',
                    schema: new OA\Schema(type: 'string', format: 'binary'),
                ),
            ),
            new OA\Response(response: 403, description: 'Missing payments.view or payments.manage right.'),
            new OA\Response(response: 404, description: 'Payment not found in the authenticated organization.'),
            new OA\Response(response: 409, description: 'Payment is not confirmed or has no receipt.'),
        ],
    )]
    public function receipt(): void
    {
    }
}
