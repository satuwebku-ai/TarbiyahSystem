<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AdminSecuritySetting extends Model
{
    protected $fillable = [
        'max_login_attempts',
        'lockout_minutes',
        'session_idle_minutes',
        'password_min_length',
        'password_require_mixed_case',
        'password_require_numbers',
        'password_require_symbols',
        'two_factor_enabled',
        'two_factor_required_admin',
        'two_factor_required_pegawai',
    ];

    protected function casts(): array
    {
        return [
            'max_login_attempts' => 'integer',
            'lockout_minutes' => 'integer',
            'session_idle_minutes' => 'integer',
            'password_min_length' => 'integer',
            'password_require_mixed_case' => 'boolean',
            'password_require_numbers' => 'boolean',
            'password_require_symbols' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_required_admin' => 'boolean',
            'two_factor_required_pegawai' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return Cache::remember('admin_security_settings', 30, function () {
            return static::query()->first()
                ?? new static(config('admin_security.defaults', []));
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('admin_security_settings');
    }
}
