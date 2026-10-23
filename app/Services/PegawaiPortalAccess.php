<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Satu-satunya aturan "siapa boleh memakai portal Pegawai/Guru".
 * Dipakai saat login dan pada setiap request, sehingga akun yang dilepas dari data pegawai,
 * pegawai yang sudah pensiun/mengundurkan diri, atau akun yang izinnya dicabut,
 * langsung kehilangan akses tanpa menunggu sesi habis.
 */
class PegawaiPortalAccess
{
    public const PERMISSION = 'attendance.self';

    public const GUARD = 'pegawai';

    public const ROLE = 'pegawai';

    public function employeeFor(User $user): ?Employee
    {
        return Employee::query()->where('user_id', $user->getKey())->first();
    }

    /** null = boleh masuk; selain itu = alasan penolakan (aman ditampilkan ke pengguna). */
    public function denialReason(User $user): ?string
    {
        if (! $user->is_active) {
            return 'Akun ini telah dinonaktifkan.';
        }

        $employee = $this->employeeFor($user);
        if (! $employee) {
            return 'Akun ini belum dihubungkan ke data pegawai. Hubungi pengelola kepegawaian.';
        }
        if ($employee->status->isEnded()) {
            return 'Status kepegawaian Anda sudah berakhir, sehingga portal tidak dapat diakses.';
        }
        if (! Gate::forUser($user)->allows(self::PERMISSION)) {
            return 'Akun ini belum diberi izin memakai portal pegawai. Hubungi pengelola.';
        }

        return null;
    }

    /** Role bawaan akun pegawai/guru: hanya boleh absen dan mengajukan koreksi milik sendiri. */
    public function defaultRole(): Role
    {
        $role = Role::firstOrCreate(
            ['name' => self::ROLE, 'guard_name' => self::GUARD],
            ['label' => 'Pegawai', 'description' => 'Akun pegawai/guru: absen dan koreksi absensi sendiri.', 'is_system' => true],
        );
        $permission = Permission::firstOrCreate(
            ['name' => self::PERMISSION, 'guard_name' => self::GUARD],
            ['label' => 'Absen & ajukan koreksi sendiri', 'group' => 'Absensi'],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        return $role;
    }
}
