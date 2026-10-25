<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\AdminSecuritySetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Menu Pengaturan Admin > Autentikasi 2 Faktor: kebijakan (aktif / wajib per jenis akun), ringkasan pemakaian,
 * dan reset 2FA akun yang kehilangan ponsel. Pendaftaran 2FA dilakukan pemilik akun di halaman profilnya.
 */
class TwoFactorSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $admins = Admin::query()->where('is_active', true);
        $users = User::query()->where('is_active', true);

        return view('admin.security.two-factor', [
            'settings' => AdminSecuritySetting::current(),
            'stats' => [
                'admin' => ['enabled' => (clone $admins)->whereNotNull('two_factor_confirmed_at')->count(), 'total' => (clone $admins)->count()],
                'pegawai' => ['enabled' => (clone $users)->whereNotNull('two_factor_confirmed_at')->count(), 'total' => (clone $users)->count()],
            ],
            'enrolledAdmins' => Admin::query()->whereNotNull('two_factor_confirmed_at')->orderBy('name')->paginate(10, ['*'], 'page_admin')->withQueryString(),
            'enrolledUsers' => User::query()->whereNotNull('two_factor_confirmed_at')->orderBy('name')->paginate(10, ['*'], 'page_pegawai')->withQueryString(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'two_factor_enabled' => ['nullable', 'boolean'],
            'two_factor_required_admin' => ['nullable', 'boolean'],
            'two_factor_required_pegawai' => ['nullable', 'boolean'],
        ]);

        $enabled = $request->boolean('two_factor_enabled');
        $data = [
            'two_factor_enabled' => $enabled,
            // "Wajib" tidak bermakna bila fitur mati: dimatikan juga agar tidak mengejutkan saat fitur diaktifkan lagi.
            'two_factor_required_admin' => $enabled && $request->boolean('two_factor_required_admin'),
            'two_factor_required_pegawai' => $enabled && $request->boolean('two_factor_required_pegawai'),
        ];

        // Cegah terkunci: mewajibkan 2FA bagi admin sebelum akun yang sedang dipakai ini sendiri mendaftar.
        $me = $request->user();
        if ($data['two_factor_required_admin'] && ! $me->hasTwoFactorEnabled()) {
            return back()->withInput()->with('error', 'Aktifkan 2FA untuk akun Anda sendiri lebih dulu (menu Profil Saya), baru wajibkan untuk semua admin.');
        }

        $settings = AdminSecuritySetting::query()->firstOrCreate(['id' => 1], config('admin_security.defaults', []));
        $settings->fill($data);
        $changed = array_values(array_diff(array_keys($settings->getDirty()), ['updated_at']));
        $settings->save();
        Cache::forget('admin_security_settings');

        if ($changed) {
            AdminAuditLog::record('security.two_factor_policy_updated', $me, $settings, ['changed_fields' => $changed]);
        }

        return back()->with('success', $changed ? 'Kebijakan 2FA disimpan.' : 'Tidak ada perubahan pada kebijakan 2FA.');
    }

    /** Hapus 2FA akun (ponsel hilang, kode pemulihan habis). Pemilik akun mendaftar ulang saat login berikutnya. */
    public function reset(Request $request, string $type, int $id): RedirectResponse
    {
        $target = $type === 'admin' ? Admin::query()->findOrFail($id) : User::query()->findOrFail($id);

        // Sama seperti mengganti sandi: hanya Super Admin yang boleh menyentuh akun Super Admin / pemegang role inti.
        if ($target instanceof Admin && ! $request->user()->isSuperAdmin()) {
            abort_if($target->isSuperAdmin() || $target->hasProtectedRole(), 403);
        }

        if (! $target->hasTwoFactorEnabled() && ! $target->hasPendingTwoFactorSetup()) {
            return back()->with('error', 'Akun ini tidak memakai 2FA.');
        }

        $target->clearTwoFactor();
        AdminAuditLog::record('security.two_factor_reset', $request->user(), $target, ['type' => $type]);

        return back()->with('success', '2FA akun '.$target->name.' direset. Bila 2FA diwajibkan, akun itu harus mendaftar ulang saat login berikutnya.');
    }
}
