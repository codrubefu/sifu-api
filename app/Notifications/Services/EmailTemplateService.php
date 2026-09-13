<?php

namespace App\Notifications\Services;

use App\Notifications\Models\EmailTemplate;
use App\Users\Models\User;

class EmailTemplateService
{
    public const DEFAULTS = [
        'account.created' => ['subject' => 'Bine ai venit la {{organization}}!', 'body' => "Bună, {{first_name}}!\nContul tău a fost creat la {{organization}}.\nSetează parola: {{setup_url}}"],
        'event.attached' => ['subject' => 'Înscriere la {{event}}', 'body' => "Bună, {{first_name}}!\nAi fost înscris la {{event}}, în data de {{starts_at}}."],
        'payment.confirmed' => ['subject' => 'Confirmare plată {{receipt_number}}', 'body' => "Bună, {{first_name}}!\nPlata de {{amount}} a fost confirmată. Chitanța {{receipt_number}} este atașată."],
    ];

    public const VARIABLES = [
        'account.created' => ['first_name', 'last_name', 'organization', 'setup_url'],
        'event.attached' => ['first_name', 'last_name', 'organization', 'event', 'starts_at'],
        'payment.confirmed' => ['first_name', 'last_name', 'organization', 'amount', 'receipt_number'],
    ];

    public function render(User $user, string $type, array $values = []): array
    {
        $template = EmailTemplate::withoutGlobalScopes()->where('organization_id', $user->organization_id)->where('type', $type)->first();
        $content = $template ? $template->only(['subject', 'body']) : self::DEFAULTS[$type];
        $values = array_merge($values, ['first_name' => $user->first_name, 'last_name' => $user->last_name, 'organization' => $user->organization?->name]);
        $replacements = [];
        foreach (self::VARIABLES[$type] as $key) {
            $replacements['{{'.$key.'}}'] = (string) ($values[$key] ?? '');
        }

        return array_map(fn (string $text) => strtr($text, $replacements), $content);
    }
}
