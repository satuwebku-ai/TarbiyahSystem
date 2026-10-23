<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminSecuritySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SecuritySettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.security.index', [
            'settings' => AdminSecuritySetting::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'max_login_attempts' => ['required', 'integer', 'between:3,20'],
            'lockout_minutes' => ['required', 'integer', 'between:1,60'],
            'session_idle_minutes' => ['required', 'integer', 'between:5,240'],
            'password_min_length' => ['required', 'integer', 'between:8,64'],
            'password_require_mixed_case' => ['nullable', 'boolean'],
            'password_require_numbers' => ['nullable', 'boolean'],
            'password_require_symbols' => ['nullable', 'boolean'],
        ]);

        foreach ([
            'password_require_mixed_case',
            'password_require_numbers',
            'password_require_symbols',
        ] as $field) {
            $data[$field] = $request->boolean($field);
        }

        $settings = AdminSecuritySetting::query()->firstOrCreate(
            ['id' => 1],
            config('admin_security.defaults', []),
        );
        $settings->fill($data);
        $changed = array_values(array_diff(array_keys($settings->getDirty()), ['updated_at']));
        $settings->save();
        Cache::forget('admin_security_settings');

        if ($changed) {
            AdminAuditLog::record('security.settings_updated', $request->user(), $settings, [
                'changed_fields' => $changed,
            ]);
        }

        return back()->with('success', $changed ? 'Kebijakan keamanan disimpan.' : 'Tidak ada perubahan pada kebijakan keamanan.');
    }

    public function activity(): View
    {
        return view('admin.security.activity', [
            'entries' => AdminAuditLog::query()->with('actor')->latest('created_at')->paginate(25),
        ]);
    }
}
