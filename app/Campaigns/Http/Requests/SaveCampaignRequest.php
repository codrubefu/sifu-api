<?php

namespace App\Campaigns\Http\Requests;

use App\Campaigns\Models\Campaign;
use App\Users\Support\OrganizationScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCampaignRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:255'],
            'channel' => [$this->isMethod('post') ? 'required' : 'sometimes', Rule::in(Campaign::CHANNELS)],
            'subject' => ['nullable', 'string', 'max:255'],
            'content' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string'],
            'segment_id' => ['nullable', 'integer', OrganizationScopedExistsRule::make('segments', 'id', $this->user()?->organization_id)],
        ];
    }
}
