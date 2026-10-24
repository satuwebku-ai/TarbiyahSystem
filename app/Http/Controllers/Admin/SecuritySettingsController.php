<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminSecuritySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use App\Support\Dt;
use Yajra\DataTables\Facades\DataTables;

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

    public function activity(Request $request): View|JsonResponse
    {
        if (Dt::wants($request)) {
            return DataTables::eloquent(AdminAuditLog::query()->with('actor'))
                ->editColumn('created_at', fn (AdminAuditLog $e) => '<span class="text-nowrap small">'.e($e->created_at?->format('d M Y H:i:s') ?? '—').'</span>')
                ->addColumn('event', fn (AdminAuditLog $e) => view('admin.security._activity-cell', ['col' => 'event', 'entry' => $e])->render())
                ->editColumn('actor_name', fn (AdminAuditLog $e) => view('admin.security._activity-cell', ['col' => 'actor_name', 'entry' => $e])->render())
                ->editColumn('subject_type', fn (AdminAuditLog $e) => '<span class="small">'.e($e->subject_type ? $e->subject_type.' #'.$e->subject_id : '—').'</span>')
                ->editColumn('ip_address', fn (AdminAuditLog $e) => '<span class="text-nowrap small">'.e($e->ip_address ?: '—').'</span>')
                ->rawColumns(['created_at', 'event', 'actor_name', 'subject_type', 'ip_address'])
                ->make(true);
        }

        return view('admin.security.activity');
    }
}
