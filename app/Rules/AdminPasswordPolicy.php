<?php

namespace App\Rules;

use App\Models\AdminSecuritySetting;
use Illuminate\Validation\Rules\Password;

class AdminPasswordPolicy
{
    public static function rule(): Password
    {
        $settings = AdminSecuritySetting::current();
        $rule = Password::min($settings->password_min_length)->letters();

        if ($settings->password_require_mixed_case) {
            $rule->mixedCase();
        }
        if ($settings->password_require_numbers) {
            $rule->numbers();
        }
        if ($settings->password_require_symbols) {
            $rule->symbols();
        }

        return $rule;
    }
}
