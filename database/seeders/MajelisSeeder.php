<?php

namespace Database\Seeders;

use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Majelis Kelembagaan beserta 12 majelisnya, di bawah Pengurus Besar. Idempoten dan aman dijalankan
 * di data nyata: php artisan db:seed --class=MajelisSeeder
 */
class MajelisSeeder extends Seeder
{
    private const MAJELIS = [
        'Majelis Pendidikan Dasar dan Menengah',
        'Majelis Pendidikan Tinggi',
        'Majelis Hubungan Luar Negeri',
        'Majelis Peranan Wanita',
        'Majelis Dakwah dan Pembinaan Umat',
        'Majelis Hukum dan Wakaf',
        'Majelis Ekonomi',
        'Majelis Pembangunan',
        'Majelis Litbag',
        'Majelis Usaha Sosial',
        'Majelis Organisasi',
        'Majelis Peranan Pemuda',
    ];

    public function run(): void
    {
        $pb = OrganizationalUnit::query()->where('code', 'PB-YAYASAN')->first()
            ?? OrganizationalUnit::query()->whereHas('type', fn ($q) => $q->where('slug', 'pengurus-besar'))->whereNull('parent_id')->orderBy('id')->first();

        if (! $pb) {
            $this->command?->warn('Unit Pengurus Besar tidak ditemukan. Buat dulu di menu Unit Organisasi.');

            return;
        }

        DB::transaction(function () use ($pb) {
            $type = OrganizationalUnitType::firstOrCreate(['slug' => 'majelis'], ['name' => 'Majelis', 'description' => 'Majelis di bawah Pengurus Besar.', 'is_active' => true]);

            $parent = OrganizationalUnit::updateOrCreate(
                ['code' => 'PB-MAJELIS'],
                ['type_id' => $type->id, 'parent_id' => $pb->id, 'name' => 'Majelis Kelembagaan', 'status' => 'active'],
            );

            foreach (self::MAJELIS as $i => $name) {
                OrganizationalUnit::updateOrCreate(
                    ['code' => sprintf('PB-MAJELIS-%02d', $i + 1)],
                    ['type_id' => $type->id, 'parent_id' => $parent->id, 'name' => $name, 'status' => 'active'],
                );
            }
        });

        $this->command?->info('Majelis Kelembagaan: 1 induk + '.count(self::MAJELIS).' majelis.');
    }
}
