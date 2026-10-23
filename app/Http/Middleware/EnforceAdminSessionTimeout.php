<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\AdminSecuritySetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceAdminSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();
        if (! $admin instanceof Admin) {
            return $next($request);
        }

        if (! $admin->is_active) {
            AdminAuditLog::record('admin.inactive_session_revoked', $admin, $admin);
            return $this->endSession($request, 'Akun ini telah dinonaktifkan.');
        }

        $lastActivity = $request->session()->get('admin_last_activity');
        $idleLimit = AdminSecuritySetting::current()->session_idle_minutes * 60;

        if ($lastActivity && now()->timestamp - (int) $lastActivity >= $idleLimit) {
            AdminAuditLog::record('admin.session_expired', $admin, $admin);
            return $this->endSession($request, 'Sesi berakhir karena tidak aktif. Silakan masuk kembali.');
        }

        $request->session()->put('admin_last_activity', now()->timestamp);

        return $next($request);
    }

    private function endSession(Request $request, string $message): Response
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', $message);
    }
}
