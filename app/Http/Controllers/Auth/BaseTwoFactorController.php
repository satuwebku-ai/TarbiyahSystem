<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Services\TwoFactorAuthenticator;
use App\Services\TwoFactorPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pendaftaran 2FA (TOTP) oleh pemilik akun sendiri, dari halaman profil: aktifkan -> pindai QR -> konfirmasi kode
 * -> simpan kode pemulihan. Mematikan 2FA atau membuat ulang kode pemulihan wajib memasukkan kata sandi.
 */
abstract class BaseTwoFactorController extends Controller
{
    abstract protected function guardName(): string; // admin | pegawai

    private function back(): RedirectResponse
    {
        return redirect(route($this->guardName().'.profile.edit').'#two-factor');
    }

    public function enable(Request $request, TwoFactorAuthenticator $totp): RedirectResponse
    {
        $user = $request->user();
        if (! TwoFactorPolicy::featureEnabled()) {
            return $this->back()->with('error', 'Autentikasi dua faktor sedang dinonaktifkan oleh pengelola.');
        }
        if ($user->hasTwoFactorEnabled()) {
            return $this->back()->with('error', '2FA sudah aktif untuk akun ini.');
        }

        // Rahasia baru tiap kali (pendaftaran yang belum dikonfirmasi diulang dari awal).
        $user->forceFill([
            'two_factor_secret' => $totp->generateSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        return $this->back();
    }

    public function confirm(Request $request, TwoFactorAuthenticator $totp): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['two_factor_code' => ['required', 'string', 'max:20']]);

        if (! TwoFactorPolicy::featureEnabled() || ! $user->hasPendingTwoFactorSetup()) {
            return $this->back()->with('error', 'Mulai dari tombol "Aktifkan 2FA" lebih dulu.');
        }

        $step = $totp->verify((string) $user->two_factor_secret, $data['two_factor_code']);
        if ($step === null) {
            return $this->back()->withErrors(['two_factor_code' => 'Kode salah. Pastikan jam ponsel akurat, lalu masukkan kode 6 digit terbaru.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step])->save();
        $codes = $user->regenerateRecoveryCodes();
        AdminAuditLog::record($this->guardName().'.two_factor_enabled', $user, $user);

        return $this->back()->with('two_factor_recovery_codes', $codes)->with('success', '2FA aktif. Simpan kode pemulihan di bawah ini.');
    }

    public function recoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate(['two_factor_password' => ['required', 'current_password:'.$this->guardName()]]);

        if (! $user->hasTwoFactorEnabled()) {
            return $this->back()->with('error', '2FA belum aktif.');
        }

        $codes = $user->regenerateRecoveryCodes();
        AdminAuditLog::record($this->guardName().'.two_factor_recovery_regenerated', $user, $user);

        return $this->back()->with('two_factor_recovery_codes', $codes)->with('success', 'Kode pemulihan baru dibuat. Kode lama tidak berlaku lagi.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate(['two_factor_password' => ['required', 'current_password:'.$this->guardName()]]);

        if (TwoFactorPolicy::isRequiredFor($user) && $user->hasTwoFactorEnabled()) {
            return $this->back()->with('error', 'Kebijakan keamanan mewajibkan 2FA untuk akun Anda, sehingga tidak bisa dimatikan.');
        }

        $wasEnabled = $user->hasTwoFactorEnabled();
        $user->clearTwoFactor();
        if ($wasEnabled) {
            AdminAuditLog::record($this->guardName().'.two_factor_disabled', $user, $user, ['by' => 'self']);
        }

        return $this->back()->with('success', $wasEnabled ? '2FA dimatikan.' : 'Pendaftaran 2FA dibatalkan.');
    }
}
