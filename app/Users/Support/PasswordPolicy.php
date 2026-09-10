<?php

namespace App\Users\Support;

use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    public static function for(string $accountType): Password
    {
        $settings = config("security.passwords.{$accountType}", config('security.passwords.operator'));
        $rule = Password::min((int) $settings['min']);

        foreach (['letters', 'mixed_case', 'numbers', 'symbols'] as $option) {
            if (filter_var($settings[$option] ?? false, FILTER_VALIDATE_BOOL)) {
                $rule->{$option === 'mixed_case' ? 'mixedCase' : $option}();
            }
        }

        return $rule;
    }
}
