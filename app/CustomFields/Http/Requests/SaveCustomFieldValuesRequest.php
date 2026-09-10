<?php

namespace App\CustomFields\Http\Requests;

use App\CustomFields\Models\CustomField;
use App\CustomFields\Services\CustomFieldDefinitionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCustomFieldValuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'values' => ['required', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $organizationId = $this->user()?->organization_id;
            $entityType = (string) $this->route('entityType');

            if (! $organizationId) {
                $validator->errors()->add('organization_id', 'An authenticated organization is required.');
                return;
            }

            $fields = app(CustomFieldDefinitionService::class)
                ->forEntityType($organizationId, $entityType)
                ->keyBy('slug');
            $submittedValues = $this->input('values', []);
            $rules = [];

            foreach ($fields as $slug => $field) {
                $rules["values.{$slug}"] = $this->rulesForField($field);

                if (in_array($field->type, [CustomField::TYPE_MULTI_SELECT, CustomField::TYPE_CHECKBOX], true)) {
                    $rules["values.{$slug}.*"] = [Rule::in($this->optionValues($field))];
                }
            }

            foreach (array_keys($submittedValues) as $slug) {
                if (! $fields->has($slug)) {
                    $validator->errors()->add("values.{$slug}", 'The selected custom field is invalid.');
                }
            }

            $valueValidator = validator($this->only('values'), $rules);

            if ($valueValidator->fails()) {
                foreach ($valueValidator->errors()->messages() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }

    private function rulesForField(CustomField $field): array
    {
        $rules = [$field->is_required ? 'required' : 'nullable'];

        $rules = array_merge($rules, match ($field->type) {
            CustomField::TYPE_TEXT, CustomField::TYPE_TEXTAREA, CustomField::TYPE_PHONE, CustomField::TYPE_FILE => ['string'],
            CustomField::TYPE_EMAIL => ['email'],
            CustomField::TYPE_NUMBER => ['numeric'],
            CustomField::TYPE_DATE => ['date_format:Y-m-d'],
            CustomField::TYPE_DATETIME => ['date'],
            CustomField::TYPE_BOOLEAN => ['boolean'],
            CustomField::TYPE_SELECT => [Rule::in($this->optionValues($field))],
            CustomField::TYPE_MULTI_SELECT, CustomField::TYPE_CHECKBOX => ['array'],
            default => [],
        });

        return array_merge($rules, $field->validation_rules ?? []);
    }

    private function optionValues(CustomField $field): array
    {
        $options = $field->options ?? [];
        $choices = $options['choices'] ?? $options['options'] ?? $options;

        return collect($choices)
            ->map(fn ($option) => is_array($option) ? ($option['value'] ?? $option['id'] ?? $option['label'] ?? null) : $option)
            ->filter(fn ($option) => $option !== null)
            ->values()
            ->all();
    }
}
