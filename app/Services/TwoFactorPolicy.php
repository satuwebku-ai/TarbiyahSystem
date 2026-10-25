<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminSecuritySetting;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/** Aturan 2FA yang berlaku (dari Kebijakan Keamanan): apakah fitur aktif dan siapa yang wajib memakainya. */
class TwoFactorPolicy
{
    /** Fitur aktif. Bila mati, 2FA tidak diminta saat login dan tidak bisa didaftarkan. */
    public static function featureEnabled(): bool
    {
        return AdminSecuritySetting::current()->two_factor_enabled;
    }

    /** Jenis akun yang mendukung 2FA (staf: admin & pegawai). */
    public static function supports(?Authenticatable $user): bool
    {
        return $user instanceof Admin || $user instanceof User;
    }

    /** 2FA diminta saat login untuk akun ini. */
    public static function challengeRequiredFor(?Authenticatable $user): bool
    {
        return self::supports($user) && self::featureEnabled() && $user->hasTwoFactorEnabled();
    }

    /** Kebijakan mewajibkan akun jenis ini memakai 2FA. */
    public static function isRequiredFor(?Authenticatable $user): bool
    {
        if (! self::supports($user) || ! self::featureEnabled()) {
            return false;
        }

        $settings = AdminSecuritySetting::current();

        return $user instanceof Admin ? $settings->two_factor_required_admin : $settings->two_factor_required_pegawai;
    }

    /** Akun wajib 2FA tetapi belum mengaktifkannya: harus mendaftar dulu sebelum memakai aplikasi. */
    public static function mustEnroll(?Authenticatable $user): bool
    {
        return self::isRequiredFor($user) && ! $user->hasTwoFactorEnabled();
    }

    public static function guardOf(Authenticatable $user): string
    {
        return $user instanceof Admin ? 'admin' : 'pegawai';
    }
}
