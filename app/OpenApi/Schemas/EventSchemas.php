<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Event',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'category_id', type: 'integer', nullable: true, example: 3),
        new OA\Property(property: 'category', ref: '#/components/schemas/EventCategory', nullable: true),
        new OA\Property(property: 'title', type: 'string', example: 'Yoga Class'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Weekly yoga session.'),
        new OA\Property(property: 'location', type: 'string', nullable: true, example: 'Room A'),
        new OA\Property(property: 'start_time', type: 'string', example: '10:00'),
        new OA\Property(property: 'end_time', type: 'string', example: '11:00'),
        new OA\Property(property: 'recurrence_type', type: 'string', enum: ['once', 'weekly', 'monthly'], example: 'weekly'),
        new OA\Property(property: 'recurrence_days', type: 'array', nullable: true, items: new OA\Items(type: 'string'), example: ['monday', 'wednesday']),
        new OA\Property(property: 'monthly_day', type: 'integer', nullable: true, example: 15),
        new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-06-01'),
        new OA\Property(property: 'end_date', type: 'string', format: 'date', nullable: true, example: '2026-12-31'),
        new OA\Property(property: 'requires_active_service', type: 'boolean', example: true),
        new OA\Property(property: 'required_service_id', type: 'integer', nullable: true, example: 2),
        new OA\Property(property: 'requires_payment', type: 'boolean', example: true, description: 'When true, the event requires a payment before participation.'),
        new OA\Property(property: 'payment_amount', type: 'string', nullable: true, example: '49.99', description: 'Required when requires_payment is true.'),
        new OA\Property(property: 'payment_type', type: 'string', nullable: true, enum: ['cash', 'card', 'bank_transfer'], example: 'card', description: 'Required when requires_payment is true.'),
        new OA\Property(property: 'organization_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'max_participants', type: 'integer', nullable: true, example: 20),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'cancelled'], example: 'active'),
        new OA\Property(property: 'occurrences_count', type: 'integer', example: 12),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'EventCategory',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'name', type: 'string', example: 'Fitness'),
        new OA\Property(property: 'color', type: 'string', nullable: true, example: '#2563eb'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Fitness and training classes.'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'events_count', type: 'integer', example: 8),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'EventOccurrence',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'event_id', type: 'integer', example: 1),
        new OA\Property(property: 'event', ref: '#/components/schemas/Event', nullable: true),
        new OA\Property(property: 'occurrence_date', type: 'string', format: 'date', example: '2026-06-01'),
        new OA\Property(property: 'start_datetime', type: 'string', format: 'date-time', example: '2026-06-01 10:00:00'),
        new OA\Property(property: 'end_datetime', type: 'string', format: 'date-time', example: '2026-06-01 11:00:00'),
        new OA\Property(property: 'status', type: 'string', enum: ['scheduled', 'cancelled', 'completed'], example: 'scheduled'),
        new OA\Property(property: 'organization_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'participant_status', type: 'string', nullable: true, enum: ['registered', 'attended', 'cancelled', 'no_show'], example: 'registered'),
        new OA\Property(property: 'registered_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Prefers front row.'),
        new OA\Property(property: 'participants_count', type: 'integer', example: 8),
        new OA\Property(property: 'available_places', type: 'integer', nullable: true, example: 12),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'EventParticipant',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 35),
        new OA\Property(property: 'first_name', type: 'string', example: 'John'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'status', type: 'string', enum: ['registered', 'attended', 'cancelled', 'no_show'], example: 'registered'),
        new OA\Property(property: 'registered_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Prefers front row.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'EventEligibleParticipant',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 35),
        new OA\Property(property: 'user_code', type: 'string', nullable: true, example: 'USR00000000000000000000000000001'),
        new OA\Property(property: 'first_name', type: 'string', example: 'John'),
        new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+40740123456'),
        new OA\Property(property: 'active', type: 'boolean', example: true),
        new OA\Property(property: 'has_active_service', type: 'boolean', example: true),
        new OA\Property(property: 'active_services', type: 'array', items: new OA\Items(type: 'object')),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StoreEventRequest',
    required: ['title', 'start_time', 'end_time', 'recurrence_type', 'start_date', 'status'],
    properties: [
        new OA\Property(property: 'title', type: 'string', example: 'Yoga Class'),
        new OA\Property(property: 'category_id', type: 'integer', nullable: true, example: 3),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Weekly yoga session.'),
        new OA\Property(property: 'location', type: 'string', nullable: true, example: 'Room A'),
        new OA\Property(property: 'start_time', type: 'string', example: '10:00'),
        new OA\Property(property: 'end_time', type: 'string', example: '11:00'),
        new OA\Property(property: 'recurrence_type', type: 'string', enum: ['once', 'weekly', 'monthly'], example: 'weekly'),
        new OA\Property(property: 'recurrence_days', type: 'array', nullable: true, items: new OA\Items(type: 'string'), example: ['monday', 'wednesday']),
        new OA\Property(property: 'monthly_day', type: 'integer', nullable: true, example: 15),
        new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-06-01'),
        new OA\Property(property: 'end_date', type: 'string', format: 'date', nullable: true, example: '2026-12-31'),
        new OA\Property(property: 'requires_active_service', type: 'boolean', example: true),
        new OA\Property(property: 'required_service_id', type: 'integer', nullable: true, example: 2),
        new OA\Property(property: 'requires_payment', type: 'boolean', example: true, description: 'When true, payment_amount and payment_type must be provided.'),
        new OA\Property(property: 'payment_amount', type: 'number', format: 'float', nullable: true, example: 49.99, description: 'Required when requires_payment is true.'),
        new OA\Property(property: 'payment_type', type: 'string', nullable: true, enum: ['cash', 'card', 'bank_transfer'], example: 'card', description: 'Required when requires_payment is true.'),
        new OA\Property(property: 'max_participants', type: 'integer', nullable: true, example: 20),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'cancelled'], example: 'active'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateEventRequest',
    allOf: [new OA\Schema(ref: '#/components/schemas/StoreEventRequest')],
)]
#[OA\Schema(
    schema: 'StoreEventCategoryRequest',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Fitness'),
        new OA\Property(property: 'color', type: 'string', nullable: true, example: '#2563eb'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Fitness and training classes.'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateEventCategoryRequest',
    allOf: [new OA\Schema(ref: '#/components/schemas/StoreEventCategoryRequest')],
)]
#[OA\Schema(
    schema: 'AddEventParticipantRequest',
    required: ['user_id'],
    properties: [
        new OA\Property(property: 'user_id', type: 'integer', example: 35),
        new OA\Property(property: 'status', type: 'string', enum: ['registered', 'attended', 'cancelled', 'no_show'], example: 'registered'),
        new OA\Property(property: 'registered_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Paid at reception.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'BulkAddEventParticipantsRequest',
    required: ['user_ids'],
    properties: [
        new OA\Property(property: 'user_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [35, 36, 37]),
        new OA\Property(property: 'status', type: 'string', enum: ['registered', 'attended', 'cancelled', 'no_show'], example: 'registered'),
        new OA\Property(property: 'registered_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Adaugati rapid din lista de eligibili.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'BulkAddEventParticipantsResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Participants added successfully.'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/EventParticipant')),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateEventParticipantRequest',
    properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['registered', 'attended', 'cancelled', 'no_show'], example: 'attended'),
        new OA\Property(property: 'registered_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'A intarziat.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StandardSuccessResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Operation completed successfully.'),
        new OA\Property(property: 'data', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Forbidden.'),
        new OA\Property(property: 'data', nullable: true),
        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'), example: []),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ValidationErrorResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The title field is required.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
        ),
    ],
    type: 'object',
)]
class EventSchemas
{
}
