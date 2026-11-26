<?php

namespace App\Events\Http\Requests;

use App\Users\Support\OrganizationScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareServiceGroups();

        $timeFields = collect(['start_time', 'end_time'])
            ->filter(fn (string $field) => is_string($this->input($field)) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $this->input($field)))
            ->mapWithKeys(fn (string $field) => [$field => substr($this->input($field), 0, 5)])
            ->all();

        if ($timeFields !== []) {
            $this->merge($timeFields);
        }

        if ($this->has('requires_payment') && ! $this->boolean('requires_payment')) {
            $this->merge([
                'payment_amount' => null,
                'payment_type' => null,
            ]);
        }
    }

    /**
     * `service_groups: number[][]` — OR inside a group, AND between groups.
     * Empty groups are dropped; without `requires_active_service` there are no groups.
     */
    private function prepareServiceGroups(): void
    {
        if ($this->has('requires_active_service') && ! $this->boolean('requires_active_service')) {
            $this->merge(['service_groups' => []]);

            return;
        }

        $groups = $this->input('service_groups');
        if ($groups === null && $this->has('service_groups')) {
            $this->merge(['service_groups' => []]);

            return;
        }

        if (! is_array($groups)) {
            return;
        }

        $this->merge(['service_groups' => array_values(array_filter(
            $groups,
            fn ($group): bool => ! (is_array($group) && $group === []) && $group !== null,
        ))]);
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'nullable',
                OrganizationScopedExistsRule::make('event_categories', 'id', $this->user()?->organization_id),
            ],
            'location_id' => ['nullable', OrganizationScopedExistsRule::make('locations', 'id', $this->user()?->organization_id)],
            'instructor_id' => ['nullable', OrganizationScopedExistsRule::make('users', 'id', $this->user()?->organization_id)],
            'group_id' => ['nullable', OrganizationScopedExistsRule::make('groups', 'id', $this->user()?->organization_id)],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i', 'after:start_time'],
            'recurrence_type' => ['sometimes', 'required', Rule::in(['once', 'weekly', 'monthly'])],
            'recurrence_days' => ['nullable', 'required_if:recurrence_type,weekly', 'array'],
            'recurrence_days.*' => [Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'monthly_day' => ['required_if:recurrence_type,monthly', 'nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'requires_active_service' => ['sometimes', 'boolean'],
            'service_groups' => ['sometimes', 'array'],
            'service_groups.*' => ['required', 'array', 'min:1'],
            'service_groups.*.*' => [
                'required',
                'integer',
                'distinct',
                OrganizationScopedExistsRule::make('services', 'id', $this->user()?->organization_id)->whereNull('deleted_at'),
            ],
            'requires_payment' => ['sometimes', 'boolean'],
            'payment_amount' => ['nullable', 'required_if:requires_payment,true', 'numeric', 'min:0'],
            'payment_type' => ['nullable', 'required_if:requires_payment,true', 'string', 'max:3'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'cancelled'])],
        ];
    }
}
