<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Services\DemoAccounts;
use App\Services\PegawaiPortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login satu klik untuk akun demo (admin / pegawai / pelajar).
 * Hanya akun yang ada di daftar DemoAccounts yang bisa dipakai, dan hanya saat DEMO_LOGIN aktif.
 */
class DemoLoginController extends Controller
{
    private const HOME = [
        'admin' => 'admin.dashboard',
        'pegawai' => 'pegawai.dashboard',
        'pelajar' => 'pelajar.dashboard',
    ];

    public function store(Request $request, string $guard, DemoAccounts $demo, PegawaiPortalAccess $pegawaiAccess): RedirectResponse
    {
        abort_unless(DemoAccounts::enabled() && isset(self::HOME[$guard]), 404);

        $data = $request->validate(['account' => ['required', 'integer']]);
        $item = $demo->find($guard, (int) $data['account']);
        abort_if($item === null, 404);

        $model = $item['model'];

        if ($guard === 'pegawai' && ($reason = $pegawaiAccess->denialReason($model))) {
            return back()->with('error', $reason);
        }

        Auth::guard($guard)->login($model);
        $request->session()->regenerate();

        $model->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        if (in_array($guard, ['admin', 'pegawai'], true)) {
            AdminAuditLog::record($guard.'.login_success', $model, $model, ['via' => 'demo']);
        }

        return redirect()->intended(route(self::HOME[$guard]));
    }
}
