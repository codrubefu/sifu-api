<?php

namespace App\Service\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Service',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Enterprise'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Enterprise service'),
        new OA\Property(property: 'type', type: 'string', enum: ['membership', 'access_pass'], example: 'membership'),
        new OA\Property(property: 'price', type: 'string', example: '99.99'),
        new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
        new OA\Property(property: 'organization_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'duration_days', type: 'integer', nullable: true, example: 365),
        new OA\Property(property: 'expiration_rule', type: 'string', enum: ['duration', 'fixed_date', 'none'], example: 'duration'),
        new OA\Property(property: 'fixed_expires_at', type: 'string', format: 'date-time', nullable: true, example: '2027-12-31T23:59:59Z'),
        new OA\Property(property: 'grace_period_days', type: 'integer', minimum: 0, example: 7),
        new OA\Property(property: 'max_accesses', type: 'integer', minimum: 1, nullable: true, example: 30),
        new OA\Property(property: 'max_users', type: 'integer', nullable: true, example: 25),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'assignment_id', type: 'integer', nullable: true, example: 10),
        new OA\Property(property: 'invoice_number', type: 'string', nullable: true, example: 'INV000001'),
        new OA\Property(property: 'bill_number', type: 'string', nullable: true, example: 'BILL000001'),
        new OA\Property(property: 'start_date', type: 'string', format: 'date-time', nullable: true, example: '2026-05-18T09:00:00Z'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true, example: '2026-06-18T09:00:00Z'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'active', 'expired', 'suspended', 'consumed', 'reserved'], nullable: true, example: 'active'),
        new OA\Property(property: 'accesses_used', type: 'integer', minimum: 0, nullable: true, example: 4),
        new OA\Property(property: 'suspended_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'resume_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'status_reason', type: 'string', nullable: true, example: 'Medical leave'),
        new OA\Property(property: 'activation_payment_id', type: 'integer', nullable: true, example: 42),
        new OA\Property(property: 'is_currently_active', type: 'boolean', nullable: true, example: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StoreServiceRequest',
    required: ['name', 'price', 'currency'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Enterprise'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Enterprise service'),
        new OA\Property(property: 'type', type: 'string', enum: ['membership', 'access_pass'], example: 'membership'),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0, example: 99.99),
        new OA\Property(property: 'currency', type: 'string', maxLength: 3, example: 'EUR'),
        new OA\Property(property: 'duration_days', type: 'integer', minimum: 1, nullable: true, example: 365),
        new OA\Property(property: 'expiration_rule', type: 'string', enum: ['duration', 'fixed_date', 'none'], example: 'duration'),
        new OA\Property(property: 'fixed_expires_at', type: 'string', format: 'date-time', nullable: true, example: '2027-12-31T23:59:59Z'),
        new OA\Property(property: 'grace_period_days', type: 'integer', minimum: 0, example: 7),
        new OA\Property(property: 'max_accesses', type: 'integer', minimum: 1, nullable: true, example: 30),
        new OA\Property(property: 'max_users', type: 'integer', minimum: 1, nullable: true, example: 25),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UpdateServiceRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Enterprise Plus'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Updated service'),
        new OA\Property(property: 'type', type: 'string', enum: ['membership', 'access_pass'], example: 'access_pass'),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0, example: 129.99),
        new OA\Property(property: 'currency', type: 'string', maxLength: 3, example: 'EUR'),
        new OA\Property(property: 'duration_days', type: 'integer', minimum: 1, nullable: true, example: 365),
        new OA\Property(property: 'expiration_rule', type: 'string', enum: ['duration', 'fixed_date', 'none'], example: 'fixed_date'),
        new OA\Property(property: 'fixed_expires_at', type: 'string', format: 'date-time', nullable: true, example: '2027-12-31T23:59:59Z'),
        new OA\Property(property: 'grace_period_days', type: 'integer', minimum: 0, example: 3),
        new OA\Property(property: 'max_accesses', type: 'integer', minimum: 1, nullable: true, example: 50),
        new OA\Property(property: 'max_users', type: 'integer', minimum: 1, nullable: true, example: 50),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ServiceAssignment',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 10),
        new OA\Property(property: 'service_id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', example: 15),
        new OA\Property(property: 'invoice_number', type: 'string', nullable: true, example: 'INV000001'),
        new OA\Property(property: 'bill_number', type: 'string', nullable: true, example: 'BILL000001'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'active', 'expired', 'suspended', 'consumed', 'reserved'], example: 'active'),
        new OA\Property(property: 'start_date', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'accesses_used', type: 'integer', minimum: 0, example: 2),
        new OA\Property(property: 'activated_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'suspended_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'resume_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'status_reason', type: 'string', nullable: true, example: 'Manual review'),
        new OA\Property(property: 'activation_payment_id', type: 'integer', nullable: true, example: 42),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ActivateServiceAssignmentRequest',
    properties: [
        new OA\Property(property: 'payment_id', type: 'integer', nullable: true, example: 42, description: 'Required for paid services. The payment must have status confirmed, belong to the same organization, and reference this exact service_user assignment.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SuspendServiceAssignmentRequest',
    required: ['reason'],
    properties: [
        new OA\Property(property: 'reason', type: 'string', maxLength: 2000, example: 'Medical leave'),
        new OA\Property(property: 'resume_at', type: 'string', format: 'date-time', nullable: true, example: '2026-09-01T09:00:00Z'),
    ],
    type: 'object',
)]
class Schemas
{
}
