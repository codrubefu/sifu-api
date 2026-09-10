<?php

namespace App\Users\Http\Requests;

use App\Users\Models\UserDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'category' => ['sometimes', 'string', Rule::in(UserDocument::CATEGORIES)],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date'],
            'location_id' => ['nullable', Rule::exists('locations', 'id')->where('organization_id', $this->user()?->organization_id)],
        ];
    }
}
