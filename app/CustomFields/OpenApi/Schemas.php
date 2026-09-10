<?php

namespace App\CustomFields\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CustomFieldType',
    description: 'Supported custom field input types and corresponding validation/storage behavior.',
    type: 'string',
    enum: [
        'text',
        'textarea',
        'number',
        'date',
        'datetime',
        'email',
        'phone',
        'select',
        'multi_select',
        'checkbox',
        'boolean',
        'file',
    ],
    example: 'select',
)]
#[OA\Schema(
    schema: 'CustomFieldOption',
    properties: [
        new OA\Property(property: 'label', type: 'string', example: 'Website'),
        new OA\Property(property: 'value', type: 'string', example: 'website'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CustomFieldOptions',
    description: 'JSON configuration for custom fields. Select, multi_select, and checkbox fields can provide choices/options.',
    properties: [
        new OA\Property(
            property: 'choices',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/CustomFieldOption'),
            example: [
                ['label' => 'Website', 'value' => 'website'],
                ['label' => 'Referral', 'value' => 'referral'],
            ],
        ),
    ],
    type: 'object',
    nullable: true,
)]
#[OA\Schema(
    schema: 'CustomField',
    description: 'A tenant-scoped reusable custom field definition for one CRM entity type.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'organization_id', type: 'integer', example: 1),
        new OA\Property(property: 'entity_type', type: 'string', example: 'contacts'),
        new OA\Property(property: 'name', type: 'string', example: 'Lead Source'),
        new OA\Property(property: 'slug', type: 'string', example: 'lead_source'),
        new OA\Property(property: 'type', ref: '#/components/schemas/CustomFieldType'),
        new OA\Property(property: 'options', ref: '#/components/schemas/CustomFieldOptions'),
        new OA\Property(
            property: 'validation_rules',
            type: 'array',
            items: new OA\Items(type: 'string'),
            nullable: true,
            example: ['max:255'],
        ),
        new OA\Property(property: 'is_required', type: 'boolean', example: true),
        new OA\Property(property: 'sort_order', type: 'integer', example: 10),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CustomFieldResponse',
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/CustomField'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CustomFieldListResponse',
    properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/CustomField'),
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StoreCustomFieldRequest',
    description: 'Payload used to create a custom field definition for the authenticated organization. The organization_id is inferred from the bearer token.',
    required: ['entity_type', 'name', 'slug', 'type'],
    properties: [
        new OA\Property(property: 'entity_type', type: 'string', maxLength: 255, example: 'contacts'),
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Lead Source'),
        new OA\Property(property: 'slug', type: 'string', maxLength: 255, example: 'lead_source'),
        new OA\Property(property: 'type', ref: '#/components/schemas/CustomFieldType'),
        new OA\Property(property: 'options', ref: '#/components/schemas/CustomFieldOptions'),
        new OA\Property(
            property: 'validation_rules',
            type: 'array',
            items: new OA\Items(type: 'string'),
            nullable: true,
            example: ['max:255'],
        ),
        new OA\Property(property: 'is_required', type: 'boolean', example: true),
        new OA\Property(property: 'sort_order', type: 'integer', minimum: 0, example: 10),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateCustomFieldRequest',
    description: 'Payload used to update an existing custom field definition. All attributes are optional for PATCH requests.',
    properties: [
        new OA\Property(property: 'entity_type', type: 'string', maxLength: 255, example: 'contacts'),
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Lead Source'),
        new OA\Property(property: 'slug', type: 'string', maxLength: 255, example: 'lead_source'),
        new OA\Property(property: 'type', ref: '#/components/schemas/CustomFieldType'),
        new OA\Property(property: 'options', ref: '#/components/schemas/CustomFieldOptions'),
        new OA\Property(
            property: 'validation_rules',
            type: 'array',
            items: new OA\Items(type: 'string'),
            nullable: true,
            example: ['max:255'],
        ),
        new OA\Property(property: 'is_required', type: 'boolean', example: true),
        new OA\Property(property: 'sort_order', type: 'integer', minimum: 0, example: 10),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SaveCustomFieldValuesRequest',
    description: 'Payload used to persist values for an entity. Keys must match custom field slugs configured for the path entityType.',
    required: ['values'],
    properties: [
        new OA\Property(
            property: 'values',
            description: 'Map of custom field slug to value. Values are validated according to field type and field-specific rules.',
            type: 'object',
            example: [
                'lead_source' => 'website',
                'score' => 42,
                'newsletter_opt_in' => true,
                'interests' => ['product_updates', 'events'],
            ],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CustomFieldValue',
    description: 'A resolved custom field/value pair returned for an entity. Values are stored internally in typed EAV columns according to field type.',
    properties: [
        new OA\Property(property: 'custom_field', ref: '#/components/schemas/CustomField'),
        new OA\Property(
            property: 'value',
            description: 'Resolved custom field value. Scalar fields return a scalar value; multi_select and checkbox return arrays.',
            nullable: true,
            oneOf: [
                new OA\Schema(type: 'string'),
                new OA\Schema(type: 'number'),
                new OA\Schema(type: 'boolean'),
                new OA\Schema(type: 'array', items: new OA\Items(type: 'string')),
            ],
            example: 'website',
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CustomFieldValueListResponse',
    properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/CustomFieldValue'),
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CustomFieldsValidationErrorResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The values.lead_source field is required.'),
        new OA\Property(
            property: 'errors',
            description: 'Laravel validation error bag keyed by request field path.',
            type: 'object',
            example: [
                'values.lead_source' => ['The values.lead_source field is required.'],
            ],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CustomFieldsErrorResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
    ],
    type: 'object',
)]
class Schemas
{
}
