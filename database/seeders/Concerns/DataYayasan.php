<?php

namespace Database\Seeders\Concerns;

/**
 * Definisi data contoh yayasan pendidikan (FIKTIF) yang dipakai bersama oleh seeder Unit, Jabatan, Sekolah,
 * Pegawai, Penugasan, dan Siswa. Deterministik: dijalankan berulang menghasilkan data yang sama.
 *
 * Menambah wilayah cukup menambah entri di provinces(); seluruh seeder mengikutinya.
 */
trait DataYayasan
{
    public const PB_CODE = 'PB-YAYASAN';
    public const MAJELIS_CODE = 'PB-MAJELIS';
    public const YEAR_NAME = '2026/2027';
    public const YEAR_START = '2026-07-13';
    public const YEAR_END = '2027-06-26';
    public const START_PENUGASAN = '2026-01-01';

    /** Siswa per kelas. */
    public const PER_CLASS = 10;

    public const MAJELIS = [
        'Majelis Pendidikan Dasar dan Menengah',
        'Majelis Pendidikan Tinggi',
        'Majelis Hubungan Luar Negeri',
        'Majelis Peranan Wanita',
        'Majelis Dakwah dan Pembinaan Umat',
        'Majelis Hukum dan Wakaf',
        'Majelis Ekonomi',
        'Majelis Pembangunan',
        'Majelis Litbang',
        'Majelis Usaha Sosial',
        'Majelis Organisasi',
        'Majelis Peranan Pemuda',
    ];

    /** [kode, nama, [[kode daerah, nama kabupaten/kota], ...]] */
    protected function provinces(): array
    {
        return [
            ['SULTENG', 'Sulawesi Tengah', [['PAL', 'Kota Palu'], ['DGL', 'Kabupaten Donggala']]],
            ['SULSEL', 'Sulawesi Selatan', [['MKS', 'Kota Makassar'], ['GWA', 'Kabupaten Gowa']]],
        ];
    }

    /** slug jenjang => [nama, urutan, [[nama kelas, label tingkat, umur siswa], ...]] */
    protected function levels(): array
    {
        return [
            'sd' => ['SD', 1, [['Kelas 1A', 'Kelas 1', 7], ['Kelas 3A', 'Kelas 3', 9], ['Kelas 5A', 'Kelas 5', 11]]],
            'smp' => ['SMP', 2, [['Kelas 7A', 'Kelas 7', 13], ['Kelas 8A', 'Kelas 8', 14], ['Kelas 9A', 'Kelas 9', 15]]],
            'sma' => ['SMA', 3, [['X IPA 1', 'Kelas X', 16], ['XI IPA 1', 'Kelas XI', 17], ['XII IPA 1', 'Kelas XII', 18]]],
        ];
    }

    /** Guru per jenjang (mata pelajaran), mengisi nomor urut 03 dst. */
    protected function subjects(): array
    {
        return [
            'sd' => ['Guru Kelas 1', 'Guru Kelas 3', 'Guru Kelas 5', 'Guru PAI', 'Guru PJOK'],
            'smp' => ['Guru Matematika', 'Guru Bahasa Indonesia', 'Guru IPA', 'Guru Bahasa Inggris', 'Guru PAI'],
            'sma' => ['Guru Matematika', 'Guru Fisika', 'Guru Biologi', 'Guru Bahasa Inggris', 'Guru Sejarah'],
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

    /** Data contoh bukan untuk database production. */
    protected function allowed(): bool
    {
        if (app()->isProduction()) {
            $this->command?->warn(class_basename(static::class).' dilewati: data contoh tidak dibuat di production.');

            return false;
        }

        return true;
    }

    /** Jalankan seeder prasyarat hanya bila datanya belum ada. */
    protected function require(bool $exists, string $seeder): void
    {
        if (! $exists) {
            $this->call($seeder);
        }
    }
}
