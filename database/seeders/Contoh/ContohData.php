<?php

namespace Database\Seeders\Contoh;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeStatusHistory;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;

/**
 * Data contoh kecil dan fiktif (1 PB, 1 KOMWIL, 1 KOMDA, 3 sekolah). Terpisah dari data demo (kode berawalan CTH-)
 * sehingga bisa dijalankan berdampingan. Tidak membuat akun login dan tidak memuat password.
 * Semua seeder idempoten: dijalankan berulang tidak menggandakan data.
 */
trait ContohData
{
    public const PB = 'CTH-PB';
    public const KOMWIL = 'CTH-KOMWIL';
    public const KOMDA = 'CTH-KOMDA';
    public const YEAR = '2026/2027';

    /** slug jenjang => [kode sekolah, nama, NPSN, nama kelas, label tingkat, umur siswa] */
    protected function sekolah(): array
    {
        return [
            'sd' => ['CTH-SD', 'SD Tarbiyah Contoh', '69900001', 'Kelas 4A', 'Kelas 4', 10],
            'smp' => ['CTH-SMP', 'SMP Tarbiyah Contoh', '69900002', 'Kelas 8A', 'Kelas 8', 14],
            'sma' => ['CTH-SMA', 'SMA Tarbiyah Contoh', '69900003', 'XI IPA 1', 'Kelas XI', 17],
        ];
    }

    protected function namaLaki(): array
    {
        return ['Ahmad Fauzi', 'Muhammad Rizki', 'Abdul Rahman', 'Yusuf Hidayat', 'Irfan Maulana', 'Hasan Basri', 'Fajar Nugroho', 'Ilham Saputra'];
    }

    protected function namaPerempuan(): array
    {
        return ['Siti Aisyah', 'Nur Hidayah', 'Dewi Lestari', 'Fitri Rahmawati', 'Khadijah Putri', 'Salma Nabila', 'Zahra Amelia', 'Hanifah Ramadhani'];
    }

    /** Nama deterministik dari angka benih. */
    protected function nama(int $seed, string $gender): string
    {
        $list = $gender === 'female' ? $this->namaPerempuan() : $this->namaLaki();

        return $list[$seed % count($list)];
    }

    protected function telepon(int $seed): string
    {
        return '0812'.str_pad((string) (1000000 + $seed * 7919 % 8999999), 8, '0', STR_PAD_LEFT);
    }

    /** Pegawai beserta penempatan dan riwayat status awal (idempoten menurut nomor pegawai). */
    protected function buatPegawai(string $nomor, string $nama, string $jenis, OrganizationalUnit $unit, ?OrganizationalPosition $jabatan, string $catatan, int $seed): Employee
    {
        $pegawai = Employee::firstOrCreate(['employee_number' => $nomor], [
            'name' => $nama, 'employee_type' => $jenis, 'status' => EmployeeStatus::Active,
            'email' => strtolower($nomor).'@pegawai.example.test', 'phone' => $this->telepon($seed),
            'address' => "Jl. Contoh No. {$seed}", 'hire_date' => (2015 + $seed % 10).'-07-15', 'notes' => $catatan,
        ]);

        if ($pegawai->wasRecentlyCreated) {
            $pegawai->assignments()->create([
                'organizational_unit_id' => $unit->id, 'organizational_position_id' => $jabatan?->id,
                'is_primary' => true, 'start_date' => '2026-01-01',
            ]);
            EmployeeStatusHistory::create([
                'employee_id' => $pegawai->id, 'from_status' => null, 'to_status' => EmployeeStatus::Active->value,
                'effective_date' => $pegawai->hire_date->toDateString(), 'reason' => 'Data contoh.',
            ]);
        }

        return $pegawai;
    }

    /** Hanya di luar production: data contoh tidak untuk database sungguhan. */
    protected function bolehJalan(): bool
    {
        if (app()->isProduction()) {
            $this->command?->warn(static::class.' dilewati: lingkungan production.');

            return false;
        }

        return true;
    }
}
