<?php

namespace App\Articles\Http\Requests;

use App\Articles\Models\Article;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:publish_at'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(Article::STATUSES)],
            'audience_segment' => ['sometimes', Rule::in(Article::AUDIENCE_SEGMENTS)],
            'segment_id' => ['nullable', 'integer', Rule::exists('segments', 'id')->where('organization_id', $this->user()->organization_id)],
            'groups' => ['sometimes', 'array'],
            'groups.*' => ['integer', Rule::exists('groups', 'id')->where('organization_id', $this->user()->organization_id)],
            'locations' => ['sometimes', 'array'],
            'locations.*' => ['integer', Rule::exists('locations', 'id')->where('organization_id', $this->user()->organization_id)],
        ];
    }
}
