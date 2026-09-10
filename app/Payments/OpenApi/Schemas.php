<?php

namespace App\Payments\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PaymentAdmin',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 34),
        new OA\Property(property: 'user_code', type: 'string', nullable: true, example: 'USR00000000000000000000000000034'),
        new OA\Property(property: 'first_name', type: 'string', example: 'Test'),
        new OA\Property(property: 'last_name', type: 'string', example: 'User'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+15550000000'),
        new OA\Property(property: 'active', type: 'boolean', example: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'test@example.com'),
        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Payment',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 8),
        new OA\Property(property: 'first_name', type: 'string', example: 'Brianne'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Ankunding'),
        new OA\Property(property: 'payment_type_id', type: 'integer', example: 1),
        new OA\Property(property: 'payment_type', type: 'string', example: 'cash', enum: ['cash', 'card', 'bank_transfer']),
        new OA\Property(property: 'organization_id', type: 'integer', example: 3, description: 'Organization inferred from the authenticated operator.'),
        new OA\Property(property: 'location_id', type: 'integer', nullable: true, example: 5, description: 'Branch inferred from the authenticated operator context.'),
        new OA\Property(property: 'status', type: 'string', example: 'confirmed', enum: ['initiated', 'pending', 'confirmed', 'failed', 'refunded', 'cancelled']),
        new OA\Property(property: 'external_reference', type: 'string', example: '0198f7d4-aad1-72bd-9d3a-0b154d548b31'),
        new OA\Property(property: 'receipt_number', type: 'string', nullable: true, example: 'CH000001'),
        new OA\Property(property: 'provider', type: 'string', nullable: true, example: 'netopia'),
        new OA\Property(property: 'provider_transaction_id', type: 'string', nullable: true, example: 'txn_83A91'),
        new OA\Property(property: 'model_type', type: 'string', example: 'service_user', default: 'service_user', enum: ['service_user', 'event_occurrence_user'], description: 'Supported payable models: service assignments and event participation assignments.'),
        new OA\Property(property: 'model_id', type: 'integer', nullable: true, example: 30, description: 'Identifier from service_user or event_occurrence_user, depending on model_type.'),
        new OA\Property(property: 'amount', type: 'string', example: '200.00'),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'confirmed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'failed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'refunded_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'cancelled_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'failure_reason', type: 'string', nullable: true, example: 'Card declined'),
        new OA\Property(property: 'admin_id', type: 'integer', example: 34),
        new OA\Property(property: 'admin', ref: '#/components/schemas/PaymentAdmin'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StorePaymentRequest',
    required: ['first_name', 'last_name', 'payment_type_id', 'model_id', 'amount', 'paid_at'],
    properties: [
        new OA\Property(property: 'first_name', type: 'string', example: 'Brianne'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Ankunding'),
        new OA\Property(property: 'payment_type_id', type: 'integer', example: 1, enum: [1, 2, 3], description: '1 = cash, 2 = card, 3 = bank_transfer.'),
        new OA\Property(property: 'model_type', type: 'string', example: 'service_user', default: 'service_user', enum: ['service_user', 'event_occurrence_user'], description: 'Use event_occurrence_user for paid event participation.'),
        new OA\Property(property: 'model_id', type: 'integer', example: 30, description: 'Plain model identifier from the selected model_type table.'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 200),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', example: '2026-06-02 21:04:00'),
        new OA\Property(property: 'external_reference', type: 'string', example: 'order-2026-00042', description: 'Optional unique merchant reference; generated automatically when omitted.'),
        new OA\Property(property: 'provider', type: 'string', example: 'netopia', description: 'Optional payment provider identifier.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AttachPaymentModelRequest',
    required: ['model_type', 'model_id'],
    properties: [
        new OA\Property(property: 'model_type', type: 'string', example: 'service_user', default: 'service_user', enum: ['service_user', 'event_occurrence_user'], description: 'Use event_occurrence_user for paid event participation.'),
        new OA\Property(property: 'model_id', type: 'integer', example: 30, description: 'Plain model identifier from the selected model_type table.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PaymentCallbackRequest',
    required: ['external_reference', 'status'],
    properties: [
        new OA\Property(property: 'external_reference', type: 'string', example: 'order-2026-00042'),
        new OA\Property(property: 'transaction_id', type: 'string', example: 'txn_83A91'),
        new OA\Property(property: 'status', type: 'string', example: 'confirmed', enum: ['pending', 'confirmed', 'failed', 'refunded', 'cancelled']),
        new OA\Property(property: 'failure_reason', type: 'string', nullable: true, example: 'Card declined'),
    ],
    type: 'object',
)]
class Schemas
{
}
