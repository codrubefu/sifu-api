<?php

namespace App\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+([._-][a-z0-9]+)*$/', 'unique:rights,name'],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
