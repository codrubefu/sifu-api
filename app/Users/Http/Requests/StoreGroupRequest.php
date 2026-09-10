<?php

namespace App\Users\Http\Requests;

use App\Users\Services\OrganizationAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'alpha_dash:ascii', 'unique:groups,name'],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'right_ids' => ['sometimes', 'array'],
            'right_ids.*' => ['integer', 'exists:rights,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $rightIds = collect($this->input('right_ids', []))
                ->filter(fn ($rightId): bool => $rightId !== null && $rightId !== '')
                ->map(fn ($rightId): int => (int) $rightId)
                ->unique()
                ->values();

            $this->rejectDisabledRights($validator, $rightIds);
        });
    }

    private function rejectDisabledRights(Validator $validator, Collection $rightIds): void
    {
        $disabledRightIds = app(OrganizationAccessService::class)
            ->disabledRightIds($rightIds, $this->user()?->organization_id);

        if ($disabledRightIds->isNotEmpty()) {
            $validator->errors()->add(
                'right_ids',
                'The selected rights include rights disabled for this organization.'
            );
        }
    }
}
