<?php

namespace App\Campaigns\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Campaign',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 12),
        new OA\Property(property: 'organization_id', type: 'integer', example: 1),
        new OA\Property(property: 'segment_id', description: 'Optional dynamic segment from the same organization.', type: 'integer', nullable: true, example: 4),
        new OA\Property(property: 'created_by', type: 'integer', example: 2),
        new OA\Property(property: 'name', type: 'string', example: 'Noutăți august'),
        new OA\Property(property: 'channel', type: 'string', enum: ['mail', 'sms'], example: 'mail'),
        new OA\Property(property: 'subject', type: 'string', nullable: true, example: 'Noutăți pentru membri'),
        new OA\Property(property: 'content', type: 'string', example: 'Conținutul campaniei.'),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'scheduled', 'sent', 'cancelled'], example: 'draft'),
        new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'cancelled_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'dispatched_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SaveCampaignRequest',
    required: ['name', 'channel', 'content'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Noutăți august'),
        new OA\Property(property: 'channel', type: 'string', enum: ['mail', 'sms'], example: 'mail'),
        new OA\Property(property: 'subject', type: 'string', nullable: true, maxLength: 255),
        new OA\Property(property: 'content', type: 'string', example: 'Conținutul campaniei.'),
        new OA\Property(property: 'segment_id', type: 'integer', nullable: true, example: 4),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CampaignStatistics',
    properties: [
        new OA\Property(property: 'total', type: 'integer', example: 120),
        new OA\Property(property: 'pending', type: 'integer', example: 4),
        new OA\Property(property: 'sent', type: 'integer', example: 110),
        new OA\Property(property: 'failed', type: 'integer', example: 2),
        new OA\Property(property: 'skipped', description: 'Recipients skipped because consent was withdrawn before delivery.', type: 'integer', example: 4),
    ],
    type: 'object',
)]
class Schemas
{
}
