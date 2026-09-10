<?php

namespace App\Sms\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SmsMessageUser',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 35),
        new OA\Property(property: 'user_code', type: 'string', nullable: true, example: 'USR00000000000000000000000000001'),
        new OA\Property(property: 'first_name', type: 'string', example: 'John'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+40722222222'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SmsMessageService',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Gold'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SmsMessage',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', nullable: true, example: 35),
        new OA\Property(property: 'service_id', type: 'integer', nullable: true, example: 2),
        new OA\Property(property: 'service_user_id', type: 'integer', nullable: true, example: 10),
        new OA\Property(property: 'type', type: 'string', example: 'service_expiring'),
        new OA\Property(property: 'destination', type: 'string', example: '0722535723'),
        new OA\Property(property: 'message', type: 'string', example: 'Abonamentul Gold expira la 2026-06-02.'),
        new OA\Property(property: 'status', type: 'string', example: 'sent'),
        new OA\Property(property: 'sent_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'user', ref: '#/components/schemas/SmsMessageUser'),
        new OA\Property(property: 'service', ref: '#/components/schemas/SmsMessageService'),
    ],
    type: 'object',
)]
class Schemas
{
}