<?php

namespace App\Models\Concerns;

use App\Services\TwoFactorAuthenticator;

/** Status dan kode pemulihan 2FA untuk akun staf (Admin, User). Kolom dibuat oleh migrasi add_two_factor_to_accounts. */
trait HasTwoFactor
{
    /** Cast kolom 2FA; dipanggil dari casts() model. */
    protected function twoFactorCasts(): array
    {
        return [
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_step' => 'integer',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && filled($this->two_factor_secret);
    }

    /** Rahasia sudah dibuat tetapi belum dikonfirmasi dengan kode dari aplikasi autentikator. */
    public function hasPendingTwoFactorSetup(): bool
    {
        return $this->two_factor_confirmed_at === null && filled($this->two_factor_secret);
    }

    public function recoveryCodesRemaining(): int
    {
        return count($this->two_factor_recovery_codes ?? []);
    }

    /**
     * Pakai satu kode pemulihan (sekali pakai). True bila cocok dan sudah dihapus dari daftar.
     */
    public function consumeRecoveryCode(string $code): bool
    {
        $auth = app(TwoFactorAuthenticator::class);
        $hash = $auth->hashRecoveryCode($code);
        $codes = $this->two_factor_recovery_codes ?? [];

        $found = null;
        foreach ($codes as $i => $stored) {
            if (hash_equals((string) $stored, $hash)) {
                $found = $i;
            }
        }
        if ($found === null) {
            return false;
        }

        unset($codes[$found]);
        $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }

    /** Buat kode pemulihan baru (menggantikan yang lama) dan kembalikan versi polosnya untuk ditampilkan sekali. */
    public function regenerateRecoveryCodes(): array
    {
        $auth = app(TwoFactorAuthenticator::class);
        $plain = $auth->generateRecoveryCodes();
        $this->forceFill(['two_factor_recovery_codes' => array_map($auth->hashRecoveryCode(...), $plain)])->save();

        return $plain;
    }

    /** Matikan 2FA dan hapus semua rahasia/kode pemulihan. */
    public function clearTwoFactor(): void
    {
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }
}
