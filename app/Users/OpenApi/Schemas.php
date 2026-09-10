<?php

namespace App\Users\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Location',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Main Office'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Headquarters'),
        new OA\Property(property: 'organization_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'location_group_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'location_group', ref: '#/components/schemas/LocationGroup', nullable: true),
        new OA\Property(property: 'users_count', type: 'integer', example: 5),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StoreLocationRequest',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Main Office'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Headquarters'),
        new OA\Property(property: 'location_group_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(
            property: 'user_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateLocationRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Main Office'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Headquarters'),
        new OA\Property(property: 'location_group_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(
            property: 'user_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
    ],
    type: 'object',
)]

#[OA\Schema(
    schema: 'LocationGroup',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'North Region'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Locations in the north region.'),
        new OA\Property(property: 'organization_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(
            property: 'locations',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Location'),
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]

#[OA\Schema(
    schema: 'StoreLocationGroupRequest',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'North Region'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Locations in the north region.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateLocationGroupRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'North Region'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Locations in the north region.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Group',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'admin'),
        new OA\Property(property: 'label', type: 'string', example: 'Administrator'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Full application access.'),
        new OA\Property(property: 'organization_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(
            property: 'rights',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Right'),
        ),
        new OA\Property(property: 'users_count', type: 'integer', example: 3),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Right',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'users.view'),
        new OA\Property(property: 'label', type: 'string', example: 'View users'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Read user records.'),
        new OA\Property(property: 'groups_count', type: 'integer', example: 2),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StoreRightRequest',
    required: ['name', 'label'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'reports.view'),
        new OA\Property(property: 'label', type: 'string', example: 'View reports'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Read report data.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateRightRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'reports.view'),
        new OA\Property(property: 'label', type: 'string', example: 'View reports'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Read report data.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StoreGroupRequest',
    required: ['name', 'label'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'manager'),
        new OA\Property(property: 'label', type: 'string', example: 'Manager'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Can view operational data.'),
        new OA\Property(
            property: 'right_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2, 3],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateGroupRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'manager'),
        new OA\Property(property: 'label', type: 'string', example: 'Manager'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Can view operational data.'),
        new OA\Property(
            property: 'right_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2, 3],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UserServiceAssignment',
    required: ['id'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-05-18'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UserServiceHistory',
    properties: [
        new OA\Property(property: 'id', type: 'integer', nullable: true, example: 10),
        new OA\Property(property: 'service_id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Enterprise'),
        new OA\Property(property: 'start_date', type: 'string', format: 'date', nullable: true, example: '2026-05-18'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date', nullable: true, example: '2026-06-18'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'NotificationConsents',
    description: 'Explicit opt-in preferences used to select notification channels. Missing or false values disable that channel.',
    properties: [
        new OA\Property(property: 'sms', description: 'Allow SMS notifications when the user has a phone number.', type: 'boolean', example: true),
        new OA\Property(property: 'mail', description: 'Allow e-mail notifications when the user has an e-mail address.', type: 'boolean', example: true),
        new OA\Property(property: 'push', description: 'Allow push notifications when the user has a push token.', type: 'boolean', example: false),
    ],
    type: 'object',
    additionalProperties: false,
)]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 35),
        new OA\Property(property: 'user_code', description: 'Unique within the authenticated organization when present.', type: 'string', nullable: true, maxLength: 32, example: 'USR00000000000000000000000000001'),
        new OA\Property(property: 'first_name', type: 'string', example: 'John'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
        new OA\Property(property: 'phone', description: 'Unique within the authenticated organization when present.', type: 'string', nullable: true, example: '+15550001111'),
        new OA\Property(property: 'notification_consents', ref: '#/components/schemas/NotificationConsents'),
        new OA\Property(property: 'push_token', description: 'Device token used for consented push notifications.', type: 'string', nullable: true, maxLength: 2048, example: 'device-token-abc123'),
        new OA\Property(property: 'active', type: 'boolean', example: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'organization_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(
            property: 'groups',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Group'),
        ),
        new OA\Property(
            property: 'locations',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Location'),
        ),
        new OA\Property(
            property: 'services',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Service'),
        ),
        new OA\Property(
            property: 'service_history',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/UserServiceHistory'),
        ),
        new OA\Property(
            property: 'active_services',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Service'),
        ),
        new OA\Property(property: 'has_active_service', type: 'boolean', example: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UserDocument',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 12),
        new OA\Property(property: 'user_id', type: 'integer', example: 35),
        new OA\Property(property: 'organization_id', type: 'integer', example: 1),
        new OA\Property(property: 'location_id', type: 'integer', nullable: true, example: 2),
        new OA\Property(property: 'category', type: 'string', enum: ['membership_request', 'identity_document', 'gdpr_agreement', 'certificate', 'contract', 'photo', 'other'], example: 'contract'),
        new OA\Property(property: 'title', type: 'string', example: 'Signed contract'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Initial signed version.'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date', nullable: true, example: '2027-01-01'),
        new OA\Property(property: 'original_name', type: 'string', example: 'contract.pdf'),
        new OA\Property(property: 'mime_type', type: 'string', example: 'application/pdf'),
        new OA\Property(property: 'extension', type: 'string', example: 'pdf'),
        new OA\Property(property: 'size', type: 'integer', example: 102400),
        new OA\Property(property: 'checksum', type: 'string', example: 'sha256hex'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'replaced', 'deleted'], example: 'active'),
        new OA\Property(property: 'uploaded_by', type: 'integer', nullable: true, example: 5),
        new OA\Property(property: 'replaces_document_id', type: 'integer', nullable: true, example: 11),
        new OA\Property(property: 'scanned_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UserDocumentUploadRequest',
    required: ['file', 'category', 'title'],
    properties: [
        new OA\Property(property: 'file', type: 'string', format: 'binary'),
        new OA\Property(property: 'category', type: 'string', enum: ['membership_request', 'identity_document', 'gdpr_agreement', 'certificate', 'contract', 'photo', 'other'], example: 'identity_document'),
        new OA\Property(property: 'title', type: 'string', example: 'Identity card'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Front and back scan.'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date', nullable: true, example: '2030-01-01'),
        new OA\Property(property: 'location_id', type: 'integer', nullable: true, example: 2),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UserDocumentDownloadUrl',
    properties: [
        new OA\Property(property: 'download_url', type: 'string', example: 'https://example.test/api/users/35/documents/12/download?expires=...'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateMePasswordRequest',
    required: ['current_password', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'current-password'),
        new OA\Property(property: 'password', description: 'Must satisfy the configured operator or administrator policy and the compromised-password check.', type: 'string', format: 'password', minLength: 8, example: 'New-Secure-Password-42!'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'new-password'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StoreUserRequest',
    required: ['first_name', 'last_name', 'email'],
    properties: [
        new OA\Property(property: 'user_code', description: 'Unique within the authenticated organization when present.', type: 'string', nullable: true, maxLength: 32, example: 'USR00000000000000000000000000001'),
        new OA\Property(property: 'first_name', type: 'string', example: 'John'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
        new OA\Property(property: 'phone', description: 'Unique within the authenticated organization when present.', type: 'string', nullable: true, example: '+15550001111'),
        new OA\Property(property: 'notification_consents', ref: '#/components/schemas/NotificationConsents'),
        new OA\Property(property: 'push_token', description: 'Device token used for push notifications.', type: 'string', nullable: true, maxLength: 2048, example: 'device-token-abc123'),
        new OA\Property(property: 'active', type: 'boolean', example: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'password', description: 'Optional. Policy depends on the target endpoint: operator policy for users/clients and administrator policy for administrators; compromised passwords are rejected.', type: 'string', format: 'password', nullable: true, minLength: 8, example: 'New-Secure-Password-42!'),
        new OA\Property(
            property: 'group_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
        new OA\Property(
            property: 'location_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
        new OA\Property(
            property: 'service_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
        new OA\Property(
            property: 'services',
            description: 'Service assignments with optional start date. Expires at is calculated automatically from the service period.',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/UserServiceAssignment'),
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateUserRequest',
    properties: [
        new OA\Property(property: 'user_code', description: 'Unique within the organization when present.', type: 'string', nullable: true, maxLength: 32, example: 'USR00000000000000000000000000001'),
        new OA\Property(property: 'first_name', type: 'string', example: 'John'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
        new OA\Property(property: 'phone', description: 'Unique within the organization when present.', type: 'string', nullable: true, example: '+15550001111'),
        new OA\Property(property: 'notification_consents', ref: '#/components/schemas/NotificationConsents'),
        new OA\Property(property: 'push_token', description: 'Device token used for push notifications; send null to remove it.', type: 'string', nullable: true, maxLength: 2048, example: 'device-token-abc123'),
        new OA\Property(property: 'active', type: 'boolean', example: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', nullable: true, example: 'new-password'),
        new OA\Property(
            property: 'group_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
        new OA\Property(
            property: 'location_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
        new OA\Property(
            property: 'service_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
        new OA\Property(
            property: 'services',
            description: 'Service assignments with optional start date. Replaces current user services when provided.',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/UserServiceAssignment'),
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SyncUserServicesRequest',
    properties: [
        new OA\Property(
            property: 'service_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2],
        ),
        new OA\Property(
            property: 'services',
            description: 'Service assignments with optional start date. Replaces current user services when provided.',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/UserServiceAssignment'),
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UserActivity',
    required: ['id', 'type', 'subject_user_id', 'model_type', 'model_id', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 42),
        new OA\Property(
            property: 'type',
            description: 'Stable business event identifier.',
            type: 'string',
            enum: [
                'user.created',
                'user.updated',
                'service.assigned',
                'service.renewed',
                'service.suspended',
                'payment.recorded',
                'approval.granted',
                'card.issued',
                'sms.sent',
            ],
            example: 'service.renewed',
        ),
        new OA\Property(property: 'actor_id', description: 'User that performed the activity, when available.', type: 'integer', nullable: true, example: 7),
        new OA\Property(property: 'subject_user_id', description: 'Member affected by the activity.', type: 'integer', example: 35),
        new OA\Property(property: 'model_type', type: 'string', example: 'App\\Service\\Models\\Service'),
        new OA\Property(property: 'model_id', type: 'integer', nullable: true, example: 3),
        new OA\Property(
            property: 'old_values',
            description: 'Sanitized values before the change. Passwords, tokens, CNP and other secrets are never returned.',
            type: 'object',
            nullable: true,
            additionalProperties: new OA\AdditionalProperties(),
            example: ['active' => false],
        ),
        new OA\Property(
            property: 'new_values',
            description: 'Sanitized values after the change. Passwords, tokens, CNP and other secrets are never returned.',
            type: 'object',
            nullable: true,
            additionalProperties: new OA\AdditionalProperties(),
            example: ['active' => true],
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-08-06T12:30:00.000000Z'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UserActivityPage',
    properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/UserActivity'),
        ),
        new OA\Property(
            property: 'links',
            properties: [
                new OA\Property(property: 'first', type: 'string', nullable: true),
                new OA\Property(property: 'last', type: 'string', nullable: true),
                new OA\Property(property: 'prev', type: 'string', nullable: true),
                new OA\Property(property: 'next', type: 'string', nullable: true),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'meta',
            properties: [
                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
                new OA\Property(property: 'last_page', type: 'integer', example: 4),
                new OA\Property(property: 'path', type: 'string', example: '/api/users/35/activity'),
                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
                new OA\Property(property: 'total', type: 'integer', example: 53),
            ],
            type: 'object',
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ConsentRecord',
    required: ['purpose', 'channel', 'policy_version', 'granted', 'occurred_at', 'source'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 81),
        new OA\Property(property: 'purpose', type: 'string', example: 'notifications'),
        new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'mail', 'push'], example: 'mail'),
        new OA\Property(property: 'policy_version', type: 'string', example: '2026-08'),
        new OA\Property(property: 'granted', description: 'False represents a withdrawal event.', type: 'boolean', example: false),
        new OA\Property(property: 'occurred_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'source', type: 'string', example: 'self_service'),
        new OA\Property(property: 'actor_id', type: 'integer', nullable: true, example: 7),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ConsentRecordRequest',
    required: ['purpose', 'channel', 'policy_version', 'granted'],
    properties: [
        new OA\Property(property: 'purpose', type: 'string', maxLength: 100, example: 'notifications'),
        new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'mail', 'push'], example: 'mail'),
        new OA\Property(property: 'policy_version', type: 'string', maxLength: 40, example: '2026-08'),
        new OA\Property(property: 'granted', type: 'boolean', example: false),
        new OA\Property(property: 'source', type: 'string', maxLength: 64, nullable: true, example: 'self_service'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'GdprRectificationRequest',
    properties: [
        new OA\Property(property: 'first_name', type: 'string', example: 'Maria'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Popescu'),
        new OA\Property(property: 'phone', description: 'Unique within the organization when present.', type: 'string', nullable: true, example: '+40722111222'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'maria@example.com'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'GdprRequest',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'type', type: 'string', enum: ['export', 'rectification', 'erasure'], example: 'erasure'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'processing', 'completed', 'failed'], example: 'pending'),
        new OA\Property(property: 'processed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'execution_proof', type: 'object', nullable: true, additionalProperties: new OA\AdditionalProperties()),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'GdprExport',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'ready', 'failed'], example: 'ready'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'download_url', description: 'Temporary signed URL, returned only while the export is ready and unexpired.', type: 'string', format: 'uri', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'GdprDataAccess',
    properties: [
        new OA\Property(property: 'profile', ref: '#/components/schemas/User'),
        new OA\Property(property: 'consents', type: 'array', items: new OA\Items(ref: '#/components/schemas/ConsentRecord')),
    ],
    type: 'object',
)]
class Schemas
{
}
