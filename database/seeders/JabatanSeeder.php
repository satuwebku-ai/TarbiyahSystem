<?php

namespace Database\Seeders;

use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use Database\Seeders\Concerns\DataYayasan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 8. Jabatan di setiap unit. Kapasitas 1 = satu pejabat; kosong (null) = tanpa batas (guru, tenaga administrasi).
 * Idempoten; kapasitas jabatan yang sudah diatur pengelola tidak ditimpa. Dilewati di production.
 */
class JabatanSeeder extends Seeder
{
    use DataYayasan;

    /** slug jenis unit => [[nama jabatan, kapasitas], ...] */
    private const BY_TYPE = [
        'pengurus-besar' => [['Ketua Umum', 1], ['Sekretaris Umum', 1], ['Bendahara Umum', 1], ['Ketua Bidang Pendidikan', 1]],
        'komwil' => [['Komisaris Wilayah', 1], ['Sekretaris Wilayah', 1], ['Bendahara Wilayah', 1]],
        'komda' => [['Komisaris Daerah', 1], ['Sekretaris Daerah', 1], ['Bendahara Daerah', 1]],
        'sekolah' => [['Kepala Sekolah', 1], ['Wakil Kepala Sekolah', 1], ['Guru', null], ['Tenaga Administrasi', null]],
    ];

    public function run(): void
    {
        if (! $this->allowed()) {
            return;
        }
        $this->require(OrganizationalUnit::where('code', self::PB_CODE)->exists(), UnitSeeder::class);

        DB::transaction(function () {
            foreach (OrganizationalUnit::with('type')->orderBy('id')->get() as $unit) {
                $slug = $unit->type->slug;
                $positions = $slug === 'majelis'
                    ? [[$unit->code === self::MAJELIS_CODE ? 'Ketua Majelis Kelembagaan' : 'Ketua Majelis', 1]]
                    : (self::BY_TYPE[$slug] ?? []);

                foreach ($positions as [$name, $capacity]) {
                    OrganizationalPosition::firstOrCreate(
                        ['organizational_unit_id' => $unit->id, 'name' => $name],
                        ['capacity' => $capacity, 'is_active' => true],
                    );
                }
            }
        });

        $this->command?->info('Jabatan: '.OrganizationalPosition::count().'.');
    }
}
