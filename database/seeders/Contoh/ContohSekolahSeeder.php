<?php

namespace Database\Seeders\Contoh;

use App\Models\AcademicYear;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\School;
use App\Models\SchoolLevel;
use Database\Seeders\SchoolLevelSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Tiga sekolah contoh (SD, SMP, SMA) di bawah KOMDA contoh, beserta jabatan sekolah dan tahun ajaran. */
class ContohSekolahSeeder extends Seeder
{
    use ContohData;

    public function run(): void
    {
        if (! $this->bolehJalan()) {
            return;
        }
        if (! OrganizationalUnit::where('code', self::KOMDA)->exists()) {
            $this->call(ContohUnitOrganisasiSeeder::class);
        }
        if (! SchoolLevel::where('slug', 'sma')->exists()) {
            $this->call(SchoolLevelSeeder::class);
        }

        DB::transaction(function () {
            $komda = OrganizationalUnit::where('code', self::KOMDA)->firstOrFail();
            $tipeId = OrganizationalUnitType::where('slug', 'sekolah')->value('id');
            $jenjang = SchoolLevel::pluck('id', 'slug');

            foreach ($this->sekolah() as $slug => [$kode, $nama, $npsn]) {
                $unit = OrganizationalUnit::updateOrCreate(
                    ['code' => $kode],
                    ['type_id' => $tipeId, 'parent_id' => $komda->id, 'name' => $nama, 'status' => 'active',
                        'address' => 'Jl. Sekolah Contoh, Palu', 'description' => 'Data contoh.'],
                );
                $sekolah = School::updateOrCreate(
                    ['organizational_unit_id' => $unit->id],
                    ['school_level_id' => $jenjang[$slug], 'npsn' => $npsn, 'phone' => $this->telepon((int) $npsn),
                        'email' => strtolower($kode).'@sekolah.example.test', 'status' => 'active'],
                );

                foreach (['Kepala Sekolah', 'Wakil Kepala Sekolah', 'Guru', 'Tenaga Administrasi'] as $jabatan) {
                    OrganizationalPosition::firstOrCreate(['organizational_unit_id' => $unit->id, 'name' => $jabatan], ['is_active' => true]);
                }
                AcademicYear::updateOrCreate(
                    ['school_id' => $sekolah->id, 'name' => self::YEAR],
                    ['start_date' => '2026-07-13', 'end_date' => '2027-06-26', 'is_active' => true],
                );
            }
        });

        $this->command?->info('Sekolah contoh: '.count($this->sekolah()).' (SD, SMP, SMA).');
    }
}
