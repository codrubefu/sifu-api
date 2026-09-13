<?php

namespace App\Notifications\Http\Requests;

use App\Notifications\Services\EmailTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['prohibited'],
            'type' => [$this->isMethod('POST') ? 'required' : 'prohibited', Rule::in(array_keys(EmailTemplateService::DEFAULTS)), Rule::unique('email_templates')->where('organization_id', $this->user()->organization_id)],
            'subject' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'required', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'body' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'required', 'string', 'max:20000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = $this->route('email_template')?->type ?? $this->input('type');
            if (! is_string($type) || ! isset(EmailTemplateService::VARIABLES[$type])) {
                return;
            }
            foreach (['subject', 'body'] as $field) {
                $text = $this->input($field);
                if (! is_string($text)) {
                    continue;
                }
                preg_match_all('/\{\{(.*?)\}\}/s', $text, $matches);
                foreach ($matches[1] as $variable) {
                    if (! in_array($variable, EmailTemplateService::VARIABLES[$type], true)) {
                        $validator->errors()->add($field, 'Unknown placeholder: '.$variable);
                    }
                }
                if ($field === 'body' && $type === 'account.created' && ! str_contains($text, '{{setup_url}}')) {
                    $validator->errors()->add('body', 'The account email must contain {{setup_url}}.');
                }
            }
        }];
    }
}
