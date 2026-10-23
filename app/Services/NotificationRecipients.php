<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menentukan siapa yang menerima notifikasi. Aturannya sama dengan aturan akses di tempat lain:
 * pemegang izin + cakupan unit (OrganizationAccess / EmployeeAccess). Akun nonaktif tidak pernah dikirimi.
 */
class NotificationRecipients
{
    public function __construct(
        private OrganizationAccess $org,
        private EmployeeAccess $employees,
    ) {}

    /** Pemegang izin (lewat role, termasuk super-admin, atau izin langsung) yang boleh mengakses unit tersebut. */
    public function approvers(string $permission, int $unitId, array $exceptAdminIds = []): Collection
    {
        return $this->holders($permission, $exceptAdminIds)
            ->filter(fn (Admin $admin) => $this->org->canAccess($admin, $unitId))
            ->values();
    }

    /** Peninjau koreksi absensi yang cakupannya meliputi pegawai tersebut. */
    public function correctionReviewers(Employee $employee, array $exceptAdminIds = []): Collection
    {
        return $this->holders('attendance.corrections.review', $exceptAdminIds)
            ->filter(fn (Admin $admin) => $this->employees->canManage($admin, $employee))
            ->values();
    }

    /** Akun pengurus (admins) aktif berdasarkan id (id kosong/duplikat diabaikan). */
    public function admins(array $ids, array $exceptAdminIds = []): Collection
    {
        $ids = collect($ids)->filter()->unique()->diff($exceptAdminIds)->values();

        return $ids->isEmpty()
            ? collect()
            : Admin::query()->whereIn('id', $ids)->where('is_active', true)->get();
    }

    /** Akun pegawai/guru aktif berdasarkan id (id kosong/duplikat diabaikan). */
    public function users(array $ids): Collection
    {
        $ids = collect($ids)->filter()->unique()->values();

        return $ids->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $ids)->where('is_active', true)->get();
    }

    public function canViewPrograms(Admin $admin): bool
    {
        return $admin->isSuperAdmin() || $admin->hasPermission('programs.view');
    }

    private function holders(string $permission, array $exceptAdminIds): Collection
    {
        return Admin::query()
            ->where('is_active', true)
            ->when($exceptAdminIds !== [], fn ($q) => $q->whereNotIn('id', array_filter($exceptAdminIds)))
            ->where(function ($q) use ($permission) {
                $q->whereHas('roles', fn ($r) => $r->where('guard_name', 'admin')->where(
                    fn ($r2) => $r2->where('name', 'super-admin')
                        ->orWhereHas('permissions', fn ($p) => $p->where('name', $permission)),
                ))->orWhereHas('directPermissions', fn ($p) => $p->where('name', $permission));
            })
            ->get();
    }
}
