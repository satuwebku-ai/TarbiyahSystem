<?php

namespace Database\Seeders\Demo;

use App\Enums\EmployeeStatus;
use App\Helpers\AttendanceClock;
use App\Models\Employee;
use App\Models\EmployeeStatusHistory;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;

/**
 * Data contoh yayasan pendidikan (FIKTIF: nama orang, NPSN, NISN, dan telepon dibuat otomatis).
 * Deterministik: dijalankan berulang menghasilkan data yang sama sehingga seeder aman diulang.
 *
 * Menambah wilayah cukup menambah entri di provinces(); seluruh seeder demo mengikutinya.
 */
trait DemoDataset
{
    public const PB_CODE = 'PB-YAYASAN';
    public const YEAR_NAME = '2026/2027';
    public const YEAR_START = '2026-07-13';
    public const YEAR_END = '2027-06-26';

    /** Pelajar per kelas contoh. */
    public const PER_CLASS = 10;

    /** [kode, nama, [[kode daerah, nama kabupaten/kota], ...]] */
    protected function provinces(): array
    {
        return [
            ['SULTENG', 'Sulawesi Tengah', [['PAL', 'Kota Palu'], ['DGL', 'Kabupaten Donggala']]],
            ['SULSEL', 'Sulawesi Selatan', [['MKS', 'Kota Makassar'], ['GWA', 'Kabupaten Gowa']]],
        ];
    }

    /** Jenjang: slug => [nama, urutan, [[nama kelas, label tingkat, umur siswa], ...]] */
    protected function levels(): array
    {
        return [
            'sd' => ['SD', 1, [['Kelas 1A', 'Kelas 1', 7], ['Kelas 3A', 'Kelas 3', 9], ['Kelas 5A', 'Kelas 5', 11]]],
            'smp' => ['SMP', 2, [['Kelas 7A', 'Kelas 7', 13], ['Kelas 8A', 'Kelas 8', 14], ['Kelas 9A', 'Kelas 9', 15]]],
            'sma' => ['SMA', 3, [['X IPA 1', 'Kelas X', 16], ['XI IPA 1', 'Kelas XI', 17], ['XII IPA 1', 'Kelas XII', 18]]],
        ];
    }

    /** SD, SMP, dan SMA pada setiap KOMDA. Indeks mulai dari 1 dan stabil menurut urutan provinces(). */
    protected function schoolSpecs(): array
    {
        $specs = [];
        $idx = 0;
        foreach ($this->provinces() as [$provCode, , $komdas]) {
            foreach ($komdas as [$komdaCode, $komdaName]) {
                $short = trim(preg_replace('/^(Kabupaten|Kota)\s+/', '', $komdaName));
                foreach ($this->levels() as $slug => [$levelName]) {
                    $idx++;
                    $specs[] = [
                        'index' => $idx,
                        'code' => strtoupper($slug)."-{$provCode}-{$komdaCode}",
                        'name' => "{$levelName} Tarbiyah {$short}",
                        'level' => $slug,
                        'komda_code' => "KOMDA-{$provCode}-{$komdaCode}",
                        'komda_short' => $short,
                    ];
                }
            }
        }

        return $specs;
    }

    /** Username akun demo dari kode unit: SD-SULTENG-PAL -> sd.sulteng.pal */
    protected function usernameFor(string $prefix, string $code): string
    {
        return $prefix.'.'.str_replace('-', '.', strtolower($code));
    }

    protected function maleNames(): array
    {
        return ['Ahmad', 'Muhammad', 'Abdul', 'Budi', 'Rahmat', 'Hasan', 'Andi', 'Yusuf', 'Ilham', 'Farid', 'Irfan', 'Syahrul', 'Zainal', 'Alwi', 'Hendra', 'Taufik', 'Ridwan', 'Fajar', 'Arif', 'Rizky', 'Haris', 'Anwar', 'Imran'];
    }

    protected function femaleNames(): array
    {
        return ['Siti', 'Nur', 'Dewi', 'Fitri', 'Rina', 'Aisyah', 'Nurhayati', 'Hartini', 'Maya', 'Wahyuni', 'Rahmawati', 'Ratna', 'Sri', 'Lestari', 'Indah', 'Aulia', 'Khadijah', 'Salma', 'Nadia', 'Putri', 'Zahra', 'Hanifah', 'Mutiara'];
    }

    protected function familyNames(): array
    {
        return ['Saputra', 'Pratama', 'Hidayat', 'Rahman', 'Kurniawan', 'Wulandari', 'Santoso', 'Lubis', 'Nasution', 'Mansur', 'Basri', 'Amir', 'Daeng', 'Hamid', 'Latif', 'Malik', 'Syam', 'Tahir', 'Usman', 'Yahya', 'Abdullah', 'Ramadhan', 'Marzuki'];
    }

    /** Nama deterministik dari angka benih. */
    protected function personName(int $seed, string $gender = 'male'): string
    {
        $first = $gender === 'female' ? $this->femaleNames() : $this->maleNames();
        $family = $this->familyNames();

        return $first[($seed * 7) % count($first)].' '.$family[($seed * 3 + intdiv($seed, 5)) % count($family)];
    }

    protected function phone(int $seed): string
    {
        return '0852'.str_pad((string) (10000000 + ($seed * 7919) % 89999999), 8, '0', STR_PAD_LEFT);
    }

    /** Angka 0-99 yang deterministik dari kunci (pengganti rand() agar data demo stabil). */
    protected function roll(string $key): int
    {
        return crc32($key) % 100;
    }

    /**
     * Hari kerja (Senin-Jumat) terakhir sebelum hari ini menurut zona absensi, urut naik.
     *
     * @return array<int, string> tanggal Y-m-d
     */
    protected function recentWorkdays(int $count = 20): array
    {
        $day = AttendanceClock::today()->subDay();
        $days = [];
        while (count($days) < $count) {
            if ($day->isWeekday()) {
                array_unshift($days, $day->toDateString());
            }
            $day = $day->subDay();
        }

        return $days;
    }

    /** Libur contoh: tanggal tetap (kalender), bukan relatif terhadap hari menjalankan seeder. */
    protected function demoHolidays(): array
    {
        return [
            '2026-08-17' => 'Hari Kemerdekaan RI',
            '2026-12-25' => 'Hari Raya Natal',
            '2027-01-01' => 'Tahun Baru Masehi',
        ];
    }

    /** Tanggal ke-14 setiap bulan yang jatuh pada hari kerja dijadikan libur contoh agar rekap menampilkan hari libur. */
    protected function isDemoHoliday(string $date): bool
    {
        return isset($this->demoHolidays()[$date]) || str_ends_with($date, '-14');
    }

    /**
     * Pegawai contoh beserta penempatan awal dan riwayat statusnya (idempoten menurut nomor pegawai).
     * Dipakai seeder struktur (pengurus) dan seeder kepegawaian (guru/staf).
     */
    protected function seedEmployee(string $number, string $name, string $type, OrganizationalUnit $unit, ?OrganizationalPosition $position, string $note, int $seed): Employee
    {
        $employee = Employee::firstOrCreate(['employee_number' => $number], [
            'name' => $name, 'employee_type' => $type, 'status' => EmployeeStatus::Active,
            'email' => strtolower($number).'@pegawai.example.test', 'phone' => $this->phone($seed),
            'address' => "Jl. Pegawai No. {$seed}", 'hire_date' => (2012 + $seed % 12).'-07-15', 'notes' => $note,
        ]);

        if ($employee->wasRecentlyCreated) {
            $hired = $employee->hire_date->toDateString();
            $employee->assignments()->create([
                'organizational_unit_id' => $unit->id, 'organizational_position_id' => $position?->id,
                'is_primary' => true, 'start_date' => $hired > '2026-01-01' ? '2026-01-01' : $hired,
            ]);
            EmployeeStatusHistory::create([
                'employee_id' => $employee->id, 'from_status' => null, 'to_status' => EmployeeStatus::Active->value,
                'effective_date' => $hired, 'reason' => 'Data contoh.',
            ]);
        }

        return $employee;
    }

    /**
     * Boleh dijalankan? Tidak di production, dan tidak di atas data demo versi lama (YayasanSeeder),
     * karena kodenya berbeda sehingga data akan bercampur atau bentrok (NPSN/NISN unik).
     */
    protected function guardProduction(): bool
    {
        if (app()->isProduction()) {
            $this->command?->warn('Seeder demo dilewati: lingkungan production.');

            return false;
        }

        if (Employee::where('employee_number', 'like', 'PEG-__-__')->exists()) {
            $this->command?->error('Data demo versi lama terdeteksi (nomor pegawai PEG-01-01 dst.), seeder demo dihentikan agar data tidak bercampur.');
            $this->command?->line('Di database LOKAL, kosongkan dulu lalu seed ulang:  php artisan migrate:fresh --force  &&  php artisan db:seed --class=DemoSeeder');

            return false;
        }

        return true;
    }

    /** Jalankan seeder prasyarat hanya bila datanya belum ada (tidak mengulang seluruh rantai). */
    protected function ensure(bool $exists, string $seeder): void
    {
        if (! $exists) {
            $this->call($seeder);
        }
    }
}
