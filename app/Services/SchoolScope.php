<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\EmployeeAssignment;
use App\Models\OrganizationalUnit;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Sekolah yang boleh dilihat seorang pengelola, lengkap dengan KOMDA dan KOMWIL-nya.
 *
 * Cakupan tetap ditentukan OrganizationAccess (unit yang ditugaskan + turunannya):
 *   - Pengurus Besar / Super Admin : semua KOMWIL, KOMDA, sekolah
 *   - KOMWIL                       : KOMDA dan sekolah di wilayahnya
 *   - KOMDA                        : sekolah di daerahnya
 *   - Kepala sekolah               : hanya sekolahnya sendiri
 * Filter KOMWIL/KOMDA hanya mempersempit di dalam cakupan itu, tidak pernah memperluas.
 */
class SchoolScope
{
    public function __construct(
        private StudentAttendanceAccess $access,
        private OrganizationAccess $organization,
    ) {}

    /** Query sekolah dalam cakupan pengelola. */
    public function query(Admin $admin): Builder
    {
        return $this->access->schools($admin);
    }

    /** Persempit query sekolah menurut KOMWIL/KOMDA terpilih (termasuk seluruh turunannya). */
    public function narrow(Builder $query, ?int $komwilId, ?int $komdaId): Builder
    {
        return $query
            ->when($komwilId, fn (Builder $q) => $q->whereIn(
                'organizational_unit_id',
                $this->organization->withDescendants([$komwilId]),
            ))
            ->when($komdaId, fn (Builder $q) => $q->whereIn(
                'organizational_unit_id',
                $this->organization->withDescendants([$komdaId]),
            ));
    }

    /**
     * Pilihan filter bertingkat dari request (komwil_id, komda_id, school_id), hanya angka positif.
     *
     * @return array{komwil: ?int, komda: ?int, school: ?int}
     */
    public function filters(Request $request): array
    {
        return [
            'komwil' => $request->integer('komwil_id') ?: null,
            'komda' => $request->integer('komda_id') ?: null,
            'school' => $request->integer('school_id') ?: null,
        ];
    }

    /**
     * Unit yang dicakup pilihan filter (beserta turunannya), SUDAH dipotong dengan cakupan akses pengelola.
     *
     * - null  : tanpa batas unit (pengelola penuh yang tidak memilih filter apa pun)
     * - []    : tidak ada unit yang cocok (mis. KOMDA tidak berada di KOMWIL yang dipilih)
     * Filter hanya mempersempit, tidak pernah memperluas. Sekolah di luar cakupan ditolak (403).
     *
     * @param  array{komwil: ?int, komda: ?int, school: ?int}  $filters
     * @param  int|null  $unitId  parameter lama `unit_id` (unit apa pun), tetap dihormati bila ada
     * @return int[]|null
     */
    public function unitScope(Admin $admin, array $filters, ?int $unitId = null): ?array
    {
        $scope = $this->organization->visibleUnitIds($admin);
        $requested = [];

        if ($filters['school']) {
            $school = $this->query($admin)->whereKey($filters['school'])->first();
            abort_if($school === null, 403);
            $requested[] = $this->organization->withDescendants([(int) $school->organizational_unit_id]);
        }
        foreach (['komda', 'komwil'] as $level) {
            if ($filters[$level]) {
                $requested[] = $this->organization->withDescendants([$filters[$level]]);
            }
        }
        if ($unitId) {
            $requested[] = $this->organization->withDescendants([$unitId]);
        }

        foreach ($requested as $set) {
            $scope = $scope === null ? $set : array_values(array_intersect($scope, $set));
        }

        return $scope;
    }

    /**
     * Unit yang "berkaitan" dengan pilihan filter, untuk daftar yang menempel pada unit
     * (hari libur, kebijakan): leluhur pilihan (berlaku ke bawah), pilihan itu sendiri, dan turunannya.
     * Null bila tidak ada filter dipilih. Kebijakan/hari libur umum (tanpa unit) ditangani pemanggil.
     *
     * @param  array{komwil: ?int, komda: ?int, school: ?int}  $filters
     * @return int[]|null
     */
    public function relatedUnitIds(Admin $admin, array $filters, ?int $unitId = null): ?array
    {
        $selected = $this->unitScope($admin, $filters, $unitId);
        $deepest = $filters['school']
            ? (int) $this->query($admin)->whereKey($filters['school'])->value('organizational_unit_id')
            : ($filters['komda'] ?: $filters['komwil'] ?: $unitId);

        if (! $deepest) {
            return null;
        }

        $ids = $selected ?? [];
        $cursor = OrganizationalUnit::query()->find($deepest);
        for ($guard = 0; $cursor && $guard < 25; $guard++) {
            $ids[] = (int) $cursor->id;
            $cursor = $cursor->parent_id ? OrganizationalUnit::query()->find($cursor->parent_id) : null;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Peta unit_id → KOMWIL/KOMDA/sekolah terdekatnya (unit sekolah dan unit di bawahnya ikut sekolah itu).
     *
     * @return array<int, array{komwil: ?int, komda: ?int, school: ?int}>
     */
    public function unitMap(): array
    {
        $units = OrganizationalUnit::query()->with('type:id,slug')->get(['id', 'parent_id', 'type_id'])->keyBy('id');
        $schoolByUnit = School::query()->pluck('id', 'organizational_unit_id');

        $map = [];
        foreach ($units as $unit) {
            $row = ['komwil' => null, 'komda' => null, 'school' => null];
            $cursor = $unit;
            for ($guard = 0; $cursor && $guard < 25; $guard++) {
                $slug = $cursor->type?->slug;
                if ($slug === 'komwil') {
                    $row['komwil'] ??= (int) $cursor->id;
                } elseif ($slug === 'komda') {
                    $row['komda'] ??= (int) $cursor->id;
                }
                if (isset($schoolByUnit[$cursor->id])) {
                    $row['school'] ??= (int) $schoolByUnit[$cursor->id];
                }
                $cursor = $cursor->parent_id ? $units->get($cursor->parent_id) : null;
            }
            $map[(int) $unit->id] = $row;
        }

        return $map;
    }

    /**
     * KOMWIL/KOMDA/sekolah tempat tiap pegawai ditempatkan (bisa lebih dari satu), untuk menyaring
     * dropdown pegawai di sisi klien. Penempatan lampau ikut dihitung, sejalan dengan EmployeeAccess.
     *
     * @param  Collection<int, mixed>  $employees  model Employee
     * @return array<int, array{komwil: int[], komda: int[], school: int[]}>  dikunci id pegawai
     */
    public function employeeAttributes(Collection $employees): array
    {
        $map = $this->unitMap();
        $attrs = [];

        EmployeeAssignment::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get(['employee_id', 'organizational_unit_id'])
            ->each(function ($assignment) use ($map, &$attrs) {
                $row = $map[(int) $assignment->organizational_unit_id] ?? null;
                $attrs[$assignment->employee_id] ??= ['komwil' => [], 'komda' => [], 'school' => []];
                foreach (['komwil', 'komda', 'school'] as $level) {
                    if ($row && $row[$level]) {
                        $attrs[$assignment->employee_id][$level][] = $row[$level];
                    }
                }
            });

        return array_map(fn (array $row) => array_map(fn (array $ids) => array_values(array_unique($ids)), $row), $attrs);
    }

    /**
     * Data untuk filter bertingkat.
     *
     * @return array{komwils: Collection<int, OrganizationalUnit>, komdas: Collection<int, OrganizationalUnit>, schools: Collection<int, array<string, mixed>>}
     */
    public function options(Admin $admin, bool $activeOnly = false): array
    {
        $schools = $this->query($admin)
            ->with(['unit.parent.type', 'unit.parent.parent.type', 'level:id,name'])
            ->when($activeOnly, fn (Builder $q) => $q->where('status', 'active'))
            ->get();

        $rows = $schools->map(function (School $school) {
            $parent = $school->unit?->parent;

            return [
                'id' => $school->id,
                'name' => (string) $school->unit?->name,
                'level' => (string) $school->level?->name,
                'komda' => $this->ancestor($parent, 'komda'),
                'komwil' => $this->ancestor($parent, 'komwil'),
            ];
        })->sortBy([
            fn ($a, $b) => strcmp($a['komwil']?->name ?? '', $b['komwil']?->name ?? ''),
            fn ($a, $b) => strcmp($a['komda']?->name ?? '', $b['komda']?->name ?? ''),
            fn ($a, $b) => strcmp($a['name'], $b['name']),
        ])->values();

        return [
            'komwils' => $rows->pluck('komwil')->filter()->unique('id')->sortBy('name')->values(),
            'komdas' => $rows->pluck('komda')->filter()->unique('id')->sortBy('name')->values(),
            'schools' => $rows->map(fn ($r) => [
                'id' => $r['id'],
                'name' => $r['name'],
                'level' => $r['level'],
                'komda_id' => $r['komda']?->id,
                'komwil_id' => $r['komwil']?->id,
            ])->values(),
        ];
    }

    private function ancestor(?OrganizationalUnit $unit, string $slug): ?OrganizationalUnit
    {
        while ($unit) {
            if ($unit->type?->slug === $slug) {
                return $unit;
            }
            $unit = $unit->parent;
        }

        return null;
    }
}
