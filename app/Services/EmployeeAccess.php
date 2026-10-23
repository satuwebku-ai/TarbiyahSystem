<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\OrganizationalUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cakupan data pegawai per unit. Pengelola berakses terbatas hanya melihat/mengelola pegawai
 * yang punya penempatan (kapan pun) di unit dalam cakupannya, termasuk unit turunan.
 */
class EmployeeAccess
{
    public function __construct(private OrganizationAccess $org) {}

    public function scopeVisible(Builder $query, Admin $admin): Builder
    {
        $visible = $this->org->visibleUnitIds($admin);
        if ($visible === null) {
            return $query;
        }

        return $query->whereHas('assignments', fn ($a) => $a->whereIn('organizational_unit_id', $visible));
    }

    public function canManage(Admin $admin, Employee $employee): bool
    {
        return $this->scopeVisible(Employee::query()->whereKey($employee->getKey()), $admin)->exists();
    }

    public function assertCanManage(Admin $admin, Employee $employee): void
    {
        abort_unless($this->canManage($admin, $employee), 403);
    }

    /** Unit aktif yang boleh dipilih pengelola ini. */
    public function availableUnits(Admin $admin): Collection
    {
        $visible = $this->org->visibleUnitIds($admin);

        return OrganizationalUnit::query()
            ->where('status', 'active')
            ->when($visible !== null, fn ($q) => $q->whereIn('id', $visible))
            ->orderBy('name')
            ->get();
    }

    /**
     * Unit untuk dropdown, berurutan seperti pohon (induk lalu turunannya).
     * `depth` = jumlah induk yang juga tersedia di daftar; `path` = rantai induk ("Induk › Anak").
     *
     * @param  Collection<int, OrganizationalUnit>  $units
     * @return array<int, array{id:int,name:string,code:string,depth:int,path:string}>
     */
    public function unitOptions(Collection $units): array
    {
        $availableIds = $units->pluck('id')->flip();
        $all = OrganizationalUnit::query()->get(['id', 'name', 'parent_id'])->keyBy('id');

        $rows = $units->map(function (OrganizationalUnit $unit) use ($all, $availableIds) {
            $chain = [];
            $depth = 0;
            $cursor = $unit;
            for ($guard = 0; $cursor && $guard < 25; $guard++) {
                array_unshift($chain, (string) $cursor->name);
                if ($cursor->id !== $unit->id && $availableIds->has($cursor->id)) {
                    $depth++;
                }
                $cursor = $cursor->parent_id ? $all->get($cursor->parent_id) : null;
            }

            return [
                'id' => $unit->id,
                'name' => (string) $unit->name,
                'code' => (string) $unit->code,
                'depth' => $depth,
                'path' => implode(' › ', array_slice($chain, 0, -1)),
                'chain' => $chain,
            ];
        })->all();

        usort($rows, function (array $a, array $b) {
            $max = max(count($a['chain']), count($b['chain']));
            for ($i = 0; $i < $max; $i++) {
                if (! isset($a['chain'][$i])) {
                    return -1; // induk lebih dulu dari turunannya
                }
                if (! isset($b['chain'][$i])) {
                    return 1;
                }
                $cmp = strnatcasecmp($a['chain'][$i], $b['chain'][$i]);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return 0;
        });

        return array_map(fn (array $row) => array_diff_key($row, ['chain' => true]), $rows);
    }
}
