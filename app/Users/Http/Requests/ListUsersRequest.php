<?php

namespace App\Users\Http\Requests;

use App\Users\Support\OrganizationScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query filters shared by GET /api/users, /api/clients and /api/administrators.
 */
class ListUsersRequest extends FormRequest
{
    public const SORTS = ['name', 'created_at', '-created_at'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'active' => ['sometimes', 'in:0,1'],
            'has_active_service' => ['sometimes', 'in:0,1'],
            'service_id' => ['sometimes', 'integer', OrganizationScopedExistsRule::make('services', 'id', $organizationId)->withoutTrashed()],
            'service_expiring_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'grade_id' => ['sometimes', 'integer', OrganizationScopedExistsRule::make('grades', 'id', $organizationId)->withoutTrashed()],
            'location_id' => ['sometimes', 'integer', OrganizationScopedExistsRule::make('locations', 'id', $organizationId)],
            'document_expired' => ['sometimes', 'in:0,1'],
            'sort' => ['sometimes', 'in:'.implode(',', self::SORTS)],
        ];
    }
}
