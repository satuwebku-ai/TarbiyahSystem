<?php

namespace Database\Seeders\Concerns;

/**
 * Data contoh yayasan pendidikan (FIKTIF — nama orang, NPSN, NISN, dan telepon dibuat otomatis).
 * Deterministik: dijalankan berulang menghasilkan data yang sama sehingga seeder aman diulang.
 *
 * Untuk menambah wilayah, cukup tambah entri di provinces(); seluruh seeder mengikutinya.
 */
trait YayasanDataset
{
    public const PB_CODE = 'PB-YAYASAN';
    public const YEAR_NAME = '2026/2027';

    /** [kode, nama, [[kode daerah, nama kabupaten/kota], ...]] */
    protected function provinces(): array
    {
        return [
            ['SULTENG', 'Sulawesi Tengah', [['TLT', 'Kabupaten Tolitoli'], ['PAL', 'Kota Palu'], ['DGL', 'Kabupaten Donggala']]],
            ['SULSEL', 'Sulawesi Selatan', [['MKS', 'Kota Makassar'], ['GWA', 'Kabupaten Gowa']]],
            ['SULBAR', 'Sulawesi Barat', [['MJU', 'Kabupaten Mamuju'], ['PLM', 'Kabupaten Polewali Mandar']]],
        ];
    }

    /** Jenjang: slug => [nama jenjang, urutan, label kelas contoh per tingkat, umur siswa per tingkat] */
    protected function levels(): array
    {
        return [
            'sd' => ['SD', 1, [['Kelas 1A', 'Kelas 1', 7], ['Kelas 3A', 'Kelas 3', 9], ['Kelas 5A', 'Kelas 5', 11]]],
            'smp' => ['SMP', 2, [['Kelas 7A', 'Kelas 7', 13], ['Kelas 8A', 'Kelas 8', 14], ['Kelas 9A', 'Kelas 9', 15]]],
            'sma' => ['SMA', 3, [['X IPA 1', 'Kelas X', 16], ['XI IPA 1', 'Kelas XI', 17], ['XII IPA 1', 'Kelas XII', 18]]],
        ];
    }

    /** Daftar sekolah (SD, SMP, SMA) pada setiap KOMDA. Indeks dimulai dari 1 dan stabil. */
    protected function schoolSpecs(): array
    {
        $specs = [];
        $idx = 0;
        foreach ($this->provinces() as [$provCode, $provName, $komdas]) {
            foreach ($komdas as [$komdaCode, $komdaName]) {
                $short = trim(preg_replace('/^(Kabupaten|Kota)\s+/', '', $komdaName));
                foreach ($this->levels() as $slug => [$levelName]) {
                    $idx++;
                    $specs[] = [
                        'index' => $idx,
                        'code' => strtoupper($slug)."-{$provCode}-{$komdaCode}",
                        'name' => "{$levelName} Yayasan {$short}",
                        'level' => $slug,
                        'komda_code' => "KOMDA-{$provCode}-{$komdaCode}",
                        'komda_short' => $short,
                    ];
                }
            }
        }

        return $specs;
    }

    protected function maleNames(): array
    {
        return ['Ahmad', 'Muhammad', 'Abdul', 'Budi', 'Rahmat', 'Hasan', 'Andi', 'Yusuf', 'Ilham', 'Farid', 'Irfan', 'Syahrul', 'Zainal', 'Alwi', 'Hendra', 'Taufik', 'Ridwan', 'Fajar', 'Arif', 'Rizky'];
    }

    protected function femaleNames(): array
    {
        return ['Siti', 'Nur', 'Dewi', 'Fitri', 'Rina', 'Aisyah', 'Nurhayati', 'Hartini', 'Maya', 'Wahyuni', 'Rahmawati', 'Ratna', 'Sri', 'Lestari', 'Indah', 'Aulia', 'Khadijah', 'Salma', 'Nadia', 'Putri'];
    }

    protected function familyNames(): array
    {
        return ['Saputra', 'Pratama', 'Hidayat', 'Rahman', 'Kurniawan', 'Wulandari', 'Santoso', 'Lubis', 'Nasution', 'Mansur', 'Basri', 'Amir', 'Daeng', 'Hamid', 'Latif', 'Malik', 'Syam', 'Tahir', 'Usman', 'Yahya', 'Abdullah', 'Ramadhan'];
    }

    /** Nama deterministik dari angka benih. */
    protected function personName(int $seed, string $gender = 'male'): string
    {
        $first = $gender === 'female' ? $this->femaleNames() : $this->maleNames();

        return $first[($seed * 7) % count($first)].' '.$this->familyNames()[($seed * 3 + intdiv($seed, 5)) % count($this->familyNames())];
    }

    protected function phone(int $seed): string
    {
        return '0852'.str_pad((string) (10000000 + ($seed * 7919) % 89999999), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Buat pegawai contoh beserta penempatan awal dan riwayat statusnya (idempoten menurut nomor pegawai).
     * Dipakai bersama oleh seeder struktur (pengurus) dan seeder kepegawaian (guru/staf).
     */
    protected function seedEmployee(string $number, string $name, string $type, \App\Models\OrganizationalUnit $unit, ?\App\Models\OrganizationalPosition $position, string $note, int $seed): \App\Models\Employee
    {
        $employee = \App\Models\Employee::firstOrCreate(['employee_number' => $number], [
            'name' => $name, 'employee_type' => $type, 'status' => \App\Enums\EmployeeStatus::Active,
            'email' => strtolower($number).'@pegawai.example.test', 'phone' => $this->phone($seed),
            'address' => "Jl. Pegawai No. {$seed}", 'hire_date' => (2012 + $seed % 12).'-07-15', 'notes' => $note,
        ]);

        if ($employee->wasRecentlyCreated) {
            $employee->assignments()->create([
                'organizational_unit_id' => $unit->id, 'organizational_position_id' => $position?->id,
                'is_primary' => true, 'start_date' => $employee->hire_date->toDateString() > '2026-01-01' ? '2026-01-01' : $employee->hire_date->toDateString(),
            ]);
            \App\Models\EmployeeStatusHistory::create([
                'employee_id' => $employee->id, 'from_status' => null, 'to_status' => \App\Enums\EmployeeStatus::Active->value,
                'effective_date' => $employee->hire_date->toDateString(), 'reason' => 'Data contoh.',
            ]);
        }

        return $employee;
    }

    /** Pastikan seeder tidak menanam data contoh ke server produksi. */
    protected function guardProduction(): bool
    {
        if (app()->isProduction()) {
            $this->command?->warn('Seeder data contoh dilewati: lingkungan produksi.');

            return false;
        }

        return true;
    }
}
