<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\AdminAuditLog;
use App\Models\AdminSecuritySetting;
use App\Services\PegawaiPortalAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Sesi portal pegawai: akun masih berhak + batas tidak aktif (memakai kebijakan keamanan yang sama dengan panel). */
class EnforcePegawaiSession
{
    public function __construct(private PegawaiPortalAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('pegawai');
        if (! $user instanceof User) {
            return $next($request);
        }

        if ($reason = $this->access->denialReason($user)) {
            AdminAuditLog::record('pegawai.session_revoked', $user, $user, ['reason' => $reason]);

            return $this->endSession($request, $reason);
        }

        $lastActivity = $request->session()->get('pegawai_last_activity');
        $idleLimit = AdminSecuritySetting::current()->session_idle_minutes * 60;

        if ($lastActivity && now()->timestamp - (int) $lastActivity >= $idleLimit) {
            AdminAuditLog::record('pegawai.session_expired', $user, $user);

            return $this->endSession($request, 'Sesi berakhir karena tidak aktif. Silakan masuk kembali.');
        }

        $request->session()->put('pegawai_last_activity', now()->timestamp);

        return $next($request);
    }

    private function endSession(Request $request, string $message): Response
    {
        Auth::guard('pegawai')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pegawai.login')->with('error', $message);
    }
}
