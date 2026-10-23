<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\OrganizationalUnit;
use App\Models\AdminAuditLog;
use App\Models\UserUnitAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationAccess
{
    /**
     * Return null for unrestricted administrators, otherwise the assigned units
     * and all descendants. An empty array deliberately means no unit access.
     */
    public function visibleUnitIds(Admin $admin): ?array
    {
        if ($admin->isSuperAdmin() || $admin->hasPermission('organization.access.all')) {
            return null;
        }

        $visible = UserUnitAccess::query()
            ->where('model_type', Admin::class)
            ->where('model_id', $admin->getKey())
            ->pluck('organizational_unit_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->withDescendants($visible);
    }

    /**
     * Return the supplied units and every descendant, without changing the
     * caller's authorization scope. Callers must intersect with visibleUnitIds.
     */
    public function withDescendants(array $unitIds): array
    {
        $visible = array_values(array_unique(array_map('intval', $unitIds)));
        $frontier = $visible;
        while ($frontier !== []) {
            $children = OrganizationalUnit::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $frontier = array_values(array_diff($children, $visible));
            $visible = array_values(array_unique([...$visible, ...$frontier]));
        }

        return $visible;
    }

    public function canAccess(Admin $admin, int $unitId): bool
    {
        $visible = $this->visibleUnitIds($admin);

        return $visible === null || in_array($unitId, $visible, true);
    }

    public function assertCanAccess(Admin $admin, int $unitId): void
    {
        abort_unless($this->canAccess($admin, $unitId), 403);
    }

    /**
     * Akun yang cakupan unitnya boleh diubah $actor. Pengelola yang bukan Super Admin tidak boleh
     * menyentuh akun Super Admin atau pemegang role inti organisasi (cakupan mereka menentukan
     * akses lintas organisasi).
     */
    public function assertCanManageTarget(Admin $actor, Admin $target): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        abort_if($target->isSuperAdmin() || $target->hasProtectedRole(), 403);
    }

    /** Tolak unit di luar cakupan $actor, kecuali $actor tidak dibatasi (Super Admin / akses semua unit). */
    public function assertWithinScope(Admin $actor, array $unitIds): void
    {
        $visible = $this->visibleUnitIds($actor);
        if ($visible === null) {
            return;
        }

        $requested = array_values(array_unique(array_map('intval', $unitIds)));
        if (array_diff($requested, $visible) !== []) {
            throw ValidationException::withMessages([
                'unit_ids' => 'Anda hanya dapat menetapkan unit yang berada dalam cakupan akses Anda sendiri.',
            ]);
        }
    }

    /**
     * Ganti cakupan unit $target oleh $actor. Pengelola terbatas hanya mengatur bagian di dalam
     * cakupannya: penetapan $target di luar cakupan $actor dipertahankan apa adanya.
     *
     * @return array<int, int> cakupan akhir $target
     */
    public function replaceScope(Admin $actor, Admin $target, array $unitIds): array
    {
        $this->assertCanManageTarget($actor, $target);
        $this->assertWithinScope($actor, $unitIds);

        $requested = array_values(array_unique(array_map('intval', $unitIds)));
        $visible = $this->visibleUnitIds($actor);

        return DB::transaction(function () use ($actor, $target, $requested, $visible) {
            $current = UserUnitAccess::query()
                ->where('model_type', Admin::class)
                ->where('model_id', $target->getKey())
                ->pluck('organizational_unit_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $kept = $visible === null ? [] : array_values(array_diff($current, $visible));
            $final = array_values(array_unique([...$kept, ...$requested]));

            UserUnitAccess::query()
                ->where('model_type', Admin::class)
                ->where('model_id', $target->getKey())
                ->delete();

            foreach ($final as $unitId) {
                UserUnitAccess::create([
                    'organizational_unit_id' => $unitId,
                    'model_type' => Admin::class,
                    'model_id' => $target->getKey(),
                    'created_at' => now(),
                ]);
            }

            AdminAuditLog::record('organization.access.updated', $actor, $target, [
                'organizational_unit_ids' => $final,
            ]);

            return $final;
        });
    }
}
