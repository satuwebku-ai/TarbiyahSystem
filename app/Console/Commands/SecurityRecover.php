<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\AppSettings;
use Illuminate\Console\Command;

/**
 * Jalan keluar bila terkunci dari login: CAPTCHA salah konfigurasi, atau akun kehilangan ponsel 2FA
 * (dan tidak ada admin lain yang bisa mereset). Dijalankan dari server.
 */
class SecurityRecover extends Command
{
    protected $signature = 'security:recover
        {--captcha : Matikan CAPTCHA (jenis = nonaktif) agar login bisa dibuka lagi}
        {--two-factor= : Reset 2FA akun dengan username ini (admin atau pegawai)}
        {--portal=admin : Portal akun untuk --two-factor: admin atau pegawai}';

    protected $description = 'Pemulihan darurat: matikan CAPTCHA atau reset 2FA sebuah akun.';

    public function handle(AppSettings $settings): int
    {
        if (! $this->option('captcha') && ! $this->option('two-factor')) {
            $this->error('Pilih --captcha dan/atau --two-factor=USERNAME.');

            return self::INVALID;
        }

        $status = self::SUCCESS;

        if ($this->option('captcha')) {
            AppSetting::query()->updateOrCreate(['key' => 'captcha_provider'], ['value' => 'off', 'is_secret' => false]);
            $settings->flush();
            AdminAuditLog::record('settings.updated', null, null, ['group' => 'captcha', 'changed_keys' => ['captcha_provider'], 'via' => 'cli']);
            $this->info('CAPTCHA dimatikan. Atur ulang di Pengaturan Aplikasi > CAPTCHA.');
        }

        if ($username = $this->option('two-factor')) {
            $portal = $this->option('portal');
            $model = match ($portal) {
                'admin' => Admin::class,
                'pegawai' => User::class,
                default => null,
            };
            $account = $model ? $model::query()->where('username', mb_strtolower(trim($username)))->first() : null;

            if (! $account) {
                $this->error("Akun \"{$username}\" tidak ditemukan di portal {$portal} (pilihan --portal: admin, pegawai).");
                $status = self::FAILURE;
            } else {
                $account->clearTwoFactor();
                AdminAuditLog::record('security.two_factor_reset', null, $account, ['type' => $portal, 'via' => 'cli']);
                $this->info("2FA akun {$account->username} direset. Ia bisa login dengan kata sandi lalu mendaftar ulang.");
            }
        }

        return $status;
    }
}
