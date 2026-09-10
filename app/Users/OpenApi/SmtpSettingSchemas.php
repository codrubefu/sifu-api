<?php

namespace App\Users\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SmtpSetting',
    required: ['id', 'organization_id', 'host', 'port', 'from_address', 'active'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'organization_id', type: 'integer', example: 1),
        new OA\Property(property: 'host', type: 'string', example: 'smtp.mailtrap.io'),
        new OA\Property(property: 'port', type: 'integer', example: 587),
        new OA\Property(property: 'username', type: 'string', nullable: true, example: 'organization-mailer'),
        new OA\Property(property: 'has_password', type: 'boolean', example: true),
        new OA\Property(property: 'encryption', type: 'string', nullable: true, enum: ['tls', 'ssl'], example: 'tls'),
        new OA\Property(property: 'from_address', type: 'string', format: 'email', example: 'no-reply@organizatie.ro'),
        new OA\Property(property: 'from_name', type: 'string', nullable: true, example: 'Organizația Mea'),
        new OA\Property(property: 'active', type: 'boolean', example: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class SmtpSettingSchemas
{
}
