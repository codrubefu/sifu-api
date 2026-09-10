<?php

namespace App\Events\Http\Requests;

use App\Payments\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $timeFields = collect(['start_time', 'end_time'])
            ->filter(fn (string $field) => is_string($this->input($field)) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $this->input($field)))
            ->mapWithKeys(fn (string $field) => [$field => substr($this->input($field), 0, 5)])
            ->all();

        if ($timeFields !== []) {
            $this->merge($timeFields);
        }

        if (! $this->boolean('requires_payment')) {
            $this->merge([
                'payment_amount' => null,
                'payment_type' => null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'nullable',
                Rule::exists('event_categories', 'id')
                    ->where(fn ($query) => $query->where('organization_id', $this->user()?->organization_id)),
            ],
            'location_id' => ['nullable', Rule::exists('locations', 'id')->where(fn ($query) => $query->where('organization_id', $this->user()?->organization_id))],
            'instructor_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('organization_id', $this->user()?->organization_id))],
            'group_id' => ['nullable', Rule::exists('groups', 'id')->where(fn ($query) => $query->where('organization_id', $this->user()?->organization_id))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'recurrence_type' => ['required', Rule::in(['once', 'weekly', 'monthly'])],
            'recurrence_days' => ['required_if:recurrence_type,weekly', 'array'],
            'recurrence_days.*' => [Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'monthly_day' => ['required_if:recurrence_type,monthly', 'nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'requires_active_service' => ['sometimes', 'boolean'],
            'required_service_id' => ['nullable', 'exists:services,id'],
            'requires_payment' => ['sometimes', 'boolean'],
            'payment_amount' => ['nullable', 'required_if:requires_payment,true', 'numeric', 'min:0'],
            'payment_type' => ['nullable', 'required_if:requires_payment,true', 'string', Rule::in(array_values(Payment::PAYMENT_TYPES))],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['active', 'inactive', 'cancelled'])],
        ];
    }
}
