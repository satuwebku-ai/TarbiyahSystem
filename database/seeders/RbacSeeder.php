<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Permission + role bawaan. AMAN untuk production dan dijalankan berulang:
 *   php artisan db:seed --class=RbacSeeder --force
 *
 * Role struktur organisasi (pengurus-besar, komisaris-wilayah, komisaris-daerah, kepala-sekolah)
 * hanya diisi izinnya saat role BARU dibuat. Role yang sudah ada tidak disentuh, sehingga
 * perubahan izin yang dibuat pengelola lewat menu Role & Permission tidak tertimpa.
 */
class RbacSeeder extends Seeder
{
    private const COMMON_VIEW = [
        'dashboard.view', 'organization.view', 'programs.view', 'reports.view', 'reports.export',
    ];

    /** Pengurus Besar: pengarah/pengawas lintas organisasi, bukan operator harian. */
    public const PB = [
        ...self::COMMON_VIEW, 'organization.access.all', 'programs.approve',
    ];

    /** Komisaris Wilayah: tata organisasi dan koordinasi program di wilayahnya; sekolah hanya dipantau. */
    public const KOMWIL = [
        ...self::COMMON_VIEW,
        'organization.units.manage', 'organization.positions.manage', 'organization.assignments.manage',
        'programs.manage', 'programs.submit', 'programs.approve',
        'education.schools.view', 'education.students.view', 'education.attendance.view',
    ];

    /** Komisaris Daerah: operasi lokal (pegawai, absensi, data pendidikan) dalam cakupan unitnya. */
    public const KOMDA = [
        ...self::COMMON_VIEW,
        'organization.units.manage', 'organization.positions.manage', 'organization.assignments.manage',
        'employees.view', 'employees.create', 'employees.update', 'employees.status',
        'employees.assignments.manage', 'employees.documents.view', 'employees.documents.manage',
        'attendance.view', 'attendance.records.manage', 'attendance.corrections.review',
        'programs.manage', 'programs.submit',
        'education.schools.view', 'education.schools.manage',
        'education.students.view', 'education.students.manage', 'education.academic.manage',
        'education.attendance.view', 'education.attendance.manage',
    ];

    /** Kepala sekolah: siswa, kelas, absensi siswa, pegawai dan absensi pegawai di sekolahnya saja. */
    public const KEPSEK = [
        'dashboard.view',
        'employees.view', 'employees.create', 'employees.update', 'employees.status',
        'employees.assignments.manage', 'employees.documents.view', 'employees.documents.manage',
        'attendance.view', 'attendance.records.manage', 'attendance.corrections.review',
        'education.schools.view',
        'education.students.view', 'education.students.manage', 'education.academic.manage',
        'education.attendance.view', 'education.attendance.manage',
    ];

    public function run(): void
    {
        Artisan::call('permissions:sync');

        Role::updateOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'admin'],
            ['label' => 'Super Admin', 'description' => 'Akses penuh ke seluruh fitur', 'is_system' => true],
        );

        $staff = Role::updateOrCreate(
            ['name' => 'staf', 'guard_name' => 'admin'],
            ['label' => 'Staf', 'description' => 'Hanya melihat dashboard', 'is_system' => false],
        );
        $staff->permissions()->syncWithoutDetaching(
            Permission::where('guard_name', 'admin')->where('name', 'dashboard.view')->pluck('id')
        );

        foreach ([
            'pengurus-besar' => ['Pengurus Besar', 'Pengarah dan pengawas lintas organisasi', self::PB],
            'komisaris-wilayah' => ['Komisaris Wilayah', 'Pengelola tingkat provinsi', self::KOMWIL],
            'komisaris-daerah' => ['Komisaris Daerah', 'Pengelola tingkat kabupaten/kota', self::KOMDA],
            'kepala-sekolah' => ['Kepala Sekolah', 'Pengelola satu sekolah', self::KEPSEK],
        ] as $name => [$label, $description, $permissions]) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'admin'],
                ['label' => $label, 'description' => $description, 'is_system' => false],
            );
            if ($role->wasRecentlyCreated) {
                $role->permissions()->sync(
                    Permission::where('guard_name', 'admin')->whereIn('name', $permissions)->pluck('id')
                );
            }
        }

        $pelajar = Role::updateOrCreate(
            ['name' => 'pelajar', 'guard_name' => 'pelajar'],
            ['label' => 'Pelajar', 'description' => 'Pelajar standar', 'is_system' => true],
        );
        $pelajar->permissions()->syncWithoutDetaching(
            Permission::where('guard_name', 'pelajar')->pluck('id')
        );
    }
}
