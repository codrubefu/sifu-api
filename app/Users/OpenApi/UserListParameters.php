<?php

namespace App\Users\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Shared query parameters for GET /users, /clients and /administrators
 * (App\Users\Http\Requests\ListUsersRequest). All optional, combined with AND.
 */
#[OA\Parameter(parameter: 'UserListActive', name: 'active', in: 'query', required: false, description: 'Account status: 1 = active accounts only, 0 = inactive only.', schema: new OA\Schema(type: 'integer', enum: [0, 1]))]
#[OA\Parameter(parameter: 'UserListHasActiveService', name: 'has_active_service', in: 'query', required: false, description: '1 = has at least one active service, 0 = has none. "Active" matches User::activeServices() after lifecycle refresh: service is_active and not deleted; assignment status active/reserved (or suspended with resume_at passed), start_date reached, not past expires_at + grace_period_days, accesses_used < max_accesses when limited.', schema: new OA\Schema(type: 'integer', enum: [0, 1]))]
#[OA\Parameter(parameter: 'UserListServiceId', name: 'service_id', in: 'query', required: false, description: 'Has an active assignment (same definition as has_active_service) of this service. Must be a non-deleted service visible to the organization, otherwise 422.', schema: new OA\Schema(type: 'integer'))]
#[OA\Parameter(parameter: 'UserListServiceExpiringDays', name: 'service_expiring_days', in: 'query', required: false, description: 'Has an active service whose expires_at is within the next N days (expires_at <= now + N). Assignments already past expires_at but still in their grace period are included; assignments without expires_at are not. Evaluated independently from service_id.', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 365))]
#[OA\Parameter(parameter: 'UserListGradeId', name: 'grade_id', in: 'query', required: false, description: 'Current grade (latest obtained_at, i.e. active_user_grade) is this grade. Must be a non-deleted grade visible to the organization, otherwise 422.', schema: new OA\Schema(type: 'integer'))]
#[OA\Parameter(parameter: 'UserListLocationId', name: 'location_id', in: 'query', required: false, description: 'User is assigned to this location. Must be a location visible to the organization, otherwise 422. Location access scope of the caller still applies.', schema: new OA\Schema(type: 'integer'))]
#[OA\Parameter(parameter: 'UserListDocumentExpired', name: 'document_expired', in: 'query', required: false, description: '1 = has at least one current (status active) document with expires_at before today, 0 = has none. Replaced/deleted documents are ignored.', schema: new OA\Schema(type: 'integer', enum: [0, 1]))]
#[OA\Parameter(parameter: 'UserListSort', name: 'sort', in: 'query', required: false, description: 'name (default: last_name, first_name), created_at (oldest first) or -created_at (newest first).', schema: new OA\Schema(type: 'string', enum: ['name', 'created_at', '-created_at'], default: 'name'))]
class UserListParameters {}
