<?php

namespace App\Console\Commands;

use App\Services\UnitAdminProvisioner;
use Illuminate\Console\Command;

/**
 * Buat akun pengelola untuk tiap unit di struktur organisasi (lihat UnitAdminProvisioner). Kata sandi sementara
 * ditulis ke satu berkas CSV privat (izin 0600): salin keluar dari server lalu HAPUS berkasnya.
 */
class ProvisionUnitAdmins extends Command
{
    protected $signature = 'admin:provision-units
        {--types= : Jenis unit dipisah koma: pengurus-besar,komwil,komda,sekolah (bawaan: semuanya)}
        {--email-domain= : Domain email akun (bawaan: admin.invalid; ganti email asli dari menu Akun Pengelola)}
        {--inactive : Buat akun dalam keadaan nonaktif (aktifkan satu per satu saat diserahkan)}
        {--dry-run : Tampilkan rencana saja, tanpa membuat akun}
        {--force : Jangan minta konfirmasi}';

    protected $description = 'Buat akun admin untuk tiap unit di struktur organisasi (peran mengikuti jenis unit, cakupan = unit itu)';

    public function handle(UnitAdminProvisioner $provisioner): int
    {
        $types = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('types')))));
        $unknown = array_diff($types, array_keys(UnitAdminProvisioner::ROLE_BY_TYPE));
        if ($unknown) {
            $this->error('Jenis unit tidak dikenal: '.implode(', ', $unknown).'. Pilihan: '.implode(', ', array_keys(UnitAdminProvisioner::ROLE_BY_TYPE)));

            return self::FAILURE;
        }

        $plan = $provisioner->plan($types ?: null, $this->option('email-domain'));
        $labels = [
            UnitAdminProvisioner::STATUS_CREATE => 'akan dibuat',
            UnitAdminProvisioner::STATUS_EXISTS => 'sudah punya akun',
            UnitAdminProvisioner::STATUS_INACTIVE => 'unit nonaktif',
            UnitAdminProvisioner::STATUS_ROLE_MISSING => 'peran belum ada (jalankan RbacSeeder)',
        ];
        $this->table(['Kode unit', 'Unit', 'Jenis', 'Peran', 'Username', 'Status'], array_map(fn ($r) => [
            $r['unit']->code, $r['unit']->name, $r['type'], $r['role']?->name ?? '-', $r['username'] ?? '-', $labels[$r['status']],
        ], $plan));

        $toCreate = count(array_filter($plan, fn ($r) => $r['status'] === UnitAdminProvisioner::STATUS_CREATE));
        if ($toCreate === 0) {
            $this->info('Tidak ada akun baru yang perlu dibuat.');

            return self::SUCCESS;
        }
        if ($this->option('dry-run')) {
            $this->info("[dry-run] {$toCreate} akun akan dibuat.");

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm("Buat {$toCreate} akun admin?")) {
            $this->warn('Dibatalkan.');

            return self::FAILURE;
        }

        $created = $provisioner->provision($plan, null, ! $this->option('inactive'));

        $path = storage_path('app/private/provisioned-admins-'.now()->format('Ymd-His').'.csv');
        @mkdir(dirname($path), 0775, true);
        touch($path);
        chmod($path, 0600); // izin dibatasi SEBELUM kata sandi ditulis
        $out = fopen($path, 'w');
        fputcsv($out, ['kode_unit', 'unit', 'jenis', 'peran', 'nama', 'username', 'email', 'password', 'status_akun']);
        foreach ($created as $c) {
            fputcsv($out, array_map([self::class, 'safeCell'], [
                $c['unit']->code, $c['unit']->name, $c['unit']->type->slug, $c['role']->name, $c['name'],
                $c['username'], $c['email'], $c['password'], $c['active'] ? 'aktif' : 'nonaktif',
            ]));
        }
        fclose($out);

        $this->info(count($created).' akun dibuat.');
        $this->warn("Kata sandi sementara ada di: {$path}");
        $this->warn('Salin berkas itu ke tempat aman lalu HAPUS dari server. Minta tiap pemegang akun mengganti kata sandi, mengisi email asli, dan mengaktifkan 2FA.');

        return self::SUCCESS;
    }

    /** Cegah rumus spreadsheet (CSV injection) bila berkas dibuka di Excel. */
    public static function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && str_contains('=+-@', $value[0]) ? "'".$value : $value;
    }
}
