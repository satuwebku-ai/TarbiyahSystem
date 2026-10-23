<?php

namespace App\Services;

use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Semua angka laporan dan dashboard dihitung dari database dan dibatasi oleh
 * cakupan unit pengguna. $scope: null = semua unit, [] = tidak ada akses.
 *
 * Setiap laporan mengembalikan:
 *   columns : [['key', 'label', 'type' => text|int|money|percent], ...]
 *   rows    : daftar array asosiatif sesuai key kolom
 *   totals  : array asosiatif total (atau null)
 *   note    : catatan cara hitung (opsional)
 */
class ReportService
{
    public const TYPES = [
        'employees' => [
            'title' => 'Ringkasan Pegawai & Guru',
            'description' => 'Jumlah pegawai dan guru per unit pada periode terpilih.',
            'can' => 'employees.view',
            'uses_period' => true,
        ],
        'employee-attendance' => [
            'title' => 'Rekap Absensi Pegawai',
            'description' => 'Hadir, terlambat, izin, sakit, cuti, dan tidak hadir per unit.',
            'can' => 'attendance.view',
            'uses_period' => true,
        ],
        'programs' => [
            'title' => 'Status Program & Kegiatan',
            'description' => 'Jumlah program menurut status dan progres kegiatan per unit.',
            'can' => 'programs.view',
            'uses_period' => true,
        ],
        'budget' => [
            'title' => 'Anggaran & Realisasi',
            'description' => 'Rencana dan realisasi anggaran kegiatan per program.',
            'can' => 'programs.view',
            'uses_period' => true,
        ],
        'schools' => [
            'title' => 'Rekap Sekolah & Siswa',
            'description' => 'Jumlah siswa dan kelas per sekolah (kondisi saat ini).',
            'can' => 'education.schools.view',
            'uses_period' => false,
        ],
        'student-attendance' => [
            'title' => 'Rekap Absensi Siswa',
            'description' => 'Kehadiran siswa per sekolah dan kelas pada periode terpilih.',
            'can' => 'education.attendance.view',
            'uses_period' => true,
        ],
    ];

    public function __construct(private OrganizationAccess $org) {}

    public function definition(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    /**
     * Gabungkan filter unit dengan cakupan akses pengguna. Hasilnya tidak pernah
     * lebih luas dari cakupan akses.
     */
    public function resolveScope(Admin $admin, ?int $unitId): ?array
    {
        $visible = $this->org->visibleUnitIds($admin);
        $requested = $unitId ? $this->org->withDescendants([$unitId]) : null;

        return match (true) {
            $visible === null => $requested,
            $requested === null => $visible,
            default => array_values(array_intersect($requested, $visible)),
        };
    }

    public function build(string $type, ?array $scope, Carbon $from, Carbon $to): array
    {
        $this->definition($type);

        return match ($type) {
            'employees' => $this->employees($scope, $from, $to),
            'employee-attendance' => $this->employeeAttendance($scope, $from, $to),
            'programs' => $this->programs($scope, $from, $to),
            'budget' => $this->budget($scope, $from, $to),
            'schools' => $this->schools($scope),
            'student-attendance' => $this->studentAttendance($scope, $from, $to),
        };
    }

    // ------------------------------------------------------------------
    // Pegawai & guru
    // ------------------------------------------------------------------

    private function employees(?array $scope, Carbon $from, Carbon $to): array
    {
        $base = DB::table('employee_assignments as a')
            ->join('employees as e', 'e.id', '=', 'a.employee_id')
            ->join('organizational_units as u', 'u.id', '=', 'a.organizational_unit_id')
            ->where('a.start_date', '<=', $to->toDateString())
            ->where(fn ($q) => $q->whereNull('a.end_date')->orWhere('a.end_date', '>=', $from->toDateString()));
        $this->scoped($base, 'a.organizational_unit_id', $scope);

        $aggregates = fn (Builder $q) => $q
            ->selectRaw('COUNT(DISTINCT e.id) as total')
            ->selectRaw("COUNT(DISTINCT CASE WHEN e.employee_type = 'teacher' THEN e.id END) as teacher")
            ->selectRaw("COUNT(DISTINCT CASE WHEN e.employee_type = 'staff' THEN e.id END) as staff")
            ->selectRaw("COUNT(DISTINCT CASE WHEN e.status = 'active' THEN e.id END) as active")
            ->selectRaw("COUNT(DISTINCT CASE WHEN e.status = 'on_leave' THEN e.id END) as on_leave")
            ->selectRaw("COUNT(DISTINCT CASE WHEN e.status IN ('resigned', 'retired') THEN e.id END) as ended");

        $rows = $aggregates((clone $base)->select(['u.id as unit_id', 'u.name as unit']))
            ->groupBy('u.id', 'u.name')
            ->orderBy('u.name')
            ->get()
            ->map(fn ($r) => $this->normalizeTotals((array) $r, ['total', 'teacher', 'staff', 'active', 'on_leave', 'ended']))
            ->all();

        // Total dihitung tersendiri agar pegawai dengan beberapa penempatan tidak terhitung ganda.
        $totals = (array) $aggregates(clone $base)->first();
        $totals['unit'] = 'Total (tanpa hitung ganda)';

        return [
            'columns' => [
                ['key' => 'unit', 'label' => 'Unit', 'type' => 'text'],
                ['key' => 'total', 'label' => 'Total', 'type' => 'int'],
                ['key' => 'teacher', 'label' => 'Guru', 'type' => 'int'],
                ['key' => 'staff', 'label' => 'Staf', 'type' => 'int'],
                ['key' => 'active', 'label' => 'Aktif', 'type' => 'int'],
                ['key' => 'on_leave', 'label' => 'Cuti panjang', 'type' => 'int'],
                ['key' => 'ended', 'label' => 'Berhenti/pensiun', 'type' => 'int'],
            ],
            'rows' => $rows,
            'totals' => $this->normalizeTotals($totals, ['total', 'teacher', 'staff', 'active', 'on_leave', 'ended']),
            'note' => 'Pegawai dihitung bila punya penempatan yang beririsan dengan periode. Status adalah status saat ini. Pegawai dengan penempatan di beberapa unit muncul di tiap unit, tetapi baris total tidak menghitungnya dua kali.',
        ];
    }

    // ------------------------------------------------------------------
    // Absensi pegawai
    // ------------------------------------------------------------------

    private function employeeAttendance(?array $scope, Carbon $from, Carbon $to): array
    {
        $query = DB::table('attendance_records as r')
            ->leftJoin('organizational_units as u', 'u.id', '=', 'r.organizational_unit_id')
            ->whereBetween('r.attendance_date', [$from->toDateString(), $to->toDateString()]);
        $this->scoped($query, 'r.organizational_unit_id', $scope);

        $rows = $query
            ->selectRaw("COALESCE(u.name, 'Tanpa unit') as unit")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN r.status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN r.status = 'late' THEN 1 ELSE 0 END) as late")
            ->selectRaw("SUM(CASE WHEN r.status = 'permit' THEN 1 ELSE 0 END) as permit")
            ->selectRaw("SUM(CASE WHEN r.status = 'sick' THEN 1 ELSE 0 END) as sick")
            ->selectRaw("SUM(CASE WHEN r.status = 'leave' THEN 1 ELSE 0 END) as leave_count")
            ->selectRaw("SUM(CASE WHEN r.status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->groupBy('u.id', 'u.name')
            ->orderBy('unit')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $sumKeys = ['total', 'present', 'late', 'permit', 'sick', 'leave_count', 'absent'];

        return $this->finish([
            ['key' => 'unit', 'label' => 'Unit', 'type' => 'text'],
            ['key' => 'total', 'label' => 'Catatan', 'type' => 'int'],
            ['key' => 'present', 'label' => 'Hadir', 'type' => 'int'],
            ['key' => 'late', 'label' => 'Terlambat', 'type' => 'int'],
            ['key' => 'permit', 'label' => 'Izin', 'type' => 'int'],
            ['key' => 'sick', 'label' => 'Sakit', 'type' => 'int'],
            ['key' => 'leave_count', 'label' => 'Cuti', 'type' => 'int'],
            ['key' => 'absent', 'label' => 'Tidak hadir', 'type' => 'int'],
            ['key' => 'rate', 'label' => 'Kehadiran (%)', 'type' => 'percent'],
        ], $rows, $sumKeys, [
            'rate' => fn (array $r) => $this->ratio(($r['present'] ?? 0) + ($r['late'] ?? 0), $r['total'] ?? 0),
        ], 'Kehadiran (%) = (hadir + terlambat) dibagi seluruh catatan absensi pada periode.');
    }

    // ------------------------------------------------------------------
    // Program kerja & anggaran
    // ------------------------------------------------------------------

    private function programs(?array $scope, Carbon $from, Carbon $to): array
    {
        $programs = DB::table('work_programs as p')
            ->join('organizational_units as u', 'u.id', '=', 'p.organizational_unit_id')
            ->where('p.start_date', '<=', $to->toDateString())
            ->where('p.end_date', '>=', $from->toDateString());
        $this->scoped($programs, 'p.organizational_unit_id', $scope);

        $rows = $programs
            ->select(['u.id as unit_id', 'u.name as unit'])
            ->selectRaw('COUNT(*) as programs')
            ->selectRaw("SUM(CASE WHEN p.status = 'draft' THEN 1 ELSE 0 END) as draft")
            ->selectRaw("SUM(CASE WHEN p.status = 'submitted' THEN 1 ELSE 0 END) as submitted")
            ->selectRaw("SUM(CASE WHEN p.status = 'approved' THEN 1 ELSE 0 END) as approved")
            ->selectRaw("SUM(CASE WHEN p.status = 'rejected' THEN 1 ELSE 0 END) as rejected")
            ->selectRaw("SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN p.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->groupBy('u.id', 'u.name')
            ->orderBy('u.name')
            ->get()
            ->keyBy('unit_id')
            ->map(fn ($r) => (array) $r);

        $activities = DB::table('work_activities as a')
            ->join('work_programs as p', 'p.id', '=', 'a.work_program_id')
            ->where('p.start_date', '<=', $to->toDateString())
            ->where('p.end_date', '>=', $from->toDateString());
        $this->scoped($activities, 'p.organizational_unit_id', $scope);

        $activityByUnit = $activities
            ->select('p.organizational_unit_id as unit_id')
            ->selectRaw('COUNT(*) as activities')
            ->selectRaw("SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as activities_done")
            ->groupBy('p.organizational_unit_id')
            ->get()
            ->keyBy('unit_id');

        $merged = $rows->map(function (array $row, $unitId) use ($activityByUnit) {
            $row['activities'] = (int) ($activityByUnit[$unitId]->activities ?? 0);
            $row['activities_done'] = (int) ($activityByUnit[$unitId]->activities_done ?? 0);

            return $row;
        })->values()->all();

        return $this->finish([
            ['key' => 'unit', 'label' => 'Unit', 'type' => 'text'],
            ['key' => 'programs', 'label' => 'Program', 'type' => 'int'],
            ['key' => 'draft', 'label' => 'Draft', 'type' => 'int'],
            ['key' => 'submitted', 'label' => 'Menunggu', 'type' => 'int'],
            ['key' => 'approved', 'label' => 'Disetujui', 'type' => 'int'],
            ['key' => 'rejected', 'label' => 'Revisi', 'type' => 'int'],
            ['key' => 'completed', 'label' => 'Selesai', 'type' => 'int'],
            ['key' => 'cancelled', 'label' => 'Batal', 'type' => 'int'],
            ['key' => 'activities', 'label' => 'Kegiatan', 'type' => 'int'],
            ['key' => 'activities_done', 'label' => 'Kegiatan selesai', 'type' => 'int'],
            ['key' => 'progress', 'label' => 'Progres kegiatan (%)', 'type' => 'percent'],
        ], $merged, [
            'programs', 'draft', 'submitted', 'approved', 'rejected', 'completed', 'cancelled', 'activities', 'activities_done',
        ], [
            'progress' => fn (array $r) => $this->ratio($r['activities_done'] ?? 0, $r['activities'] ?? 0),
        ], 'Program dihitung bila masa berlakunya beririsan dengan periode.');
    }

    private function budget(?array $scope, Carbon $from, Carbon $to): array
    {
        $query = DB::table('activity_budget_items as i')
            ->join('work_activities as a', 'a.id', '=', 'i.work_activity_id')
            ->join('work_programs as p', 'p.id', '=', 'a.work_program_id')
            ->join('organizational_units as u', 'u.id', '=', 'p.organizational_unit_id')
            ->where('p.start_date', '<=', $to->toDateString())
            ->where('p.end_date', '>=', $from->toDateString());
        $this->scoped($query, 'p.organizational_unit_id', $scope);

        $rows = $query
            ->select(['p.id as program_id', 'p.code', 'p.title', 'u.name as unit'])
            ->selectRaw('SUM(i.planned_amount) as planned')
            ->selectRaw('SUM(i.realized_amount) as realized')
            ->groupBy('p.id', 'p.code', 'p.title', 'u.name')
            ->orderBy('u.name')
            ->orderBy('p.code')
            ->get()
            ->map(function ($r) {
                $row = (array) $r;
                $row['program'] = $row['code'].' — '.$row['title'];
                $row['planned'] = (float) $row['planned'];
                $row['realized'] = (float) $row['realized'];
                $row['difference'] = $row['planned'] - $row['realized'];

                return $row;
            })
            ->all();

        return $this->finish([
            ['key' => 'unit', 'label' => 'Unit', 'type' => 'text'],
            ['key' => 'program', 'label' => 'Program', 'type' => 'text'],
            ['key' => 'planned', 'label' => 'Rencana', 'type' => 'money'],
            ['key' => 'realized', 'label' => 'Realisasi', 'type' => 'money'],
            ['key' => 'difference', 'label' => 'Selisih', 'type' => 'money'],
            ['key' => 'usage', 'label' => 'Realisasi (%)', 'type' => 'percent'],
        ], $rows, ['planned', 'realized', 'difference'], [
            'usage' => fn (array $r) => $this->ratio($r['realized'] ?? 0, $r['planned'] ?? 0),
        ], 'Dihitung dari rincian anggaran seluruh kegiatan program. Selisih = rencana dikurangi realisasi.');
    }

    // ------------------------------------------------------------------
    // Sekolah & siswa
    // ------------------------------------------------------------------

    private function schools(?array $scope): array
    {
        $query = DB::table('schools as s')
            ->join('organizational_units as u', 'u.id', '=', 's.organizational_unit_id')
            ->join('school_levels as l', 'l.id', '=', 's.school_level_id');
        $this->scoped($query, 's.organizational_unit_id', $scope);

        $rows = $query
            ->select(['u.name as school', 'l.name as level'])
            ->selectRaw("(SELECT COUNT(*) FROM students st WHERE st.school_id = s.id AND st.status = 'active') as students_active")
            ->selectRaw('(SELECT COUNT(*) FROM students st2 WHERE st2.school_id = s.id) as students_total')
            ->selectRaw('(SELECT COUNT(*) FROM classrooms c WHERE c.school_id = s.id) as classrooms')
            ->selectRaw('(SELECT ay.name FROM academic_years ay WHERE ay.school_id = s.id AND ay.is_active = 1 ORDER BY ay.start_date DESC LIMIT 1) as active_year')
            ->orderBy('u.name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return $this->finish([
            ['key' => 'school', 'label' => 'Sekolah', 'type' => 'text'],
            ['key' => 'level', 'label' => 'Jenjang', 'type' => 'text'],
            ['key' => 'active_year', 'label' => 'Tahun ajaran aktif', 'type' => 'text'],
            ['key' => 'students_active', 'label' => 'Siswa aktif', 'type' => 'int'],
            ['key' => 'students_total', 'label' => 'Total siswa', 'type' => 'int'],
            ['key' => 'classrooms', 'label' => 'Kelas', 'type' => 'int'],
        ], $rows, ['students_active', 'students_total', 'classrooms'], [], 'Kondisi saat laporan dibuka; tidak dipengaruhi filter periode.');
    }

    // ------------------------------------------------------------------
    // Absensi siswa
    // ------------------------------------------------------------------

    private function studentAttendance(?array $scope, Carbon $from, Carbon $to): array
    {
        $query = DB::table('student_attendance_records as r')
            ->join('student_attendance_sessions as ss', 'ss.id', '=', 'r.student_attendance_session_id')
            ->join('classrooms as c', 'c.id', '=', 'ss.classroom_id')
            ->join('schools as s', 's.id', '=', 'c.school_id')
            ->join('organizational_units as u', 'u.id', '=', 's.organizational_unit_id')
            ->whereBetween('ss.attendance_date', [$from->toDateString(), $to->toDateString()]);
        $this->scoped($query, 's.organizational_unit_id', $scope);

        $rows = $query
            ->select(['u.name as school', 'c.name as classroom'])
            ->selectRaw('COUNT(DISTINCT ss.id) as sessions')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN r.status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN r.status = 'late' THEN 1 ELSE 0 END) as late")
            ->selectRaw("SUM(CASE WHEN r.status = 'permit' THEN 1 ELSE 0 END) as permit")
            ->selectRaw("SUM(CASE WHEN r.status = 'sick' THEN 1 ELSE 0 END) as sick")
            ->selectRaw("SUM(CASE WHEN r.status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->groupBy('s.id', 'u.name', 'c.id', 'c.name')
            ->orderBy('u.name')
            ->orderBy('c.name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return $this->finish([
            ['key' => 'school', 'label' => 'Sekolah', 'type' => 'text'],
            ['key' => 'classroom', 'label' => 'Kelas', 'type' => 'text'],
            ['key' => 'sessions', 'label' => 'Sesi', 'type' => 'int'],
            ['key' => 'total', 'label' => 'Catatan', 'type' => 'int'],
            ['key' => 'present', 'label' => 'Hadir', 'type' => 'int'],
            ['key' => 'late', 'label' => 'Terlambat', 'type' => 'int'],
            ['key' => 'permit', 'label' => 'Izin', 'type' => 'int'],
            ['key' => 'sick', 'label' => 'Sakit', 'type' => 'int'],
            ['key' => 'absent', 'label' => 'Alpa', 'type' => 'int'],
            ['key' => 'rate', 'label' => 'Kehadiran (%)', 'type' => 'percent'],
        ], $rows, ['sessions', 'total', 'present', 'late', 'permit', 'sick', 'absent'], [
            'rate' => fn (array $r) => $this->ratio(($r['present'] ?? 0) + ($r['late'] ?? 0), $r['total'] ?? 0),
        ], 'Kehadiran (%) = (hadir + terlambat) dibagi seluruh catatan siswa pada periode.');
    }

    // ------------------------------------------------------------------
    // Util
    // ------------------------------------------------------------------

    private function scoped(Builder $query, string $column, ?array $scope): Builder
    {
        return $scope === null ? $query : $query->whereIn($column, $scope);
    }

    private function ratio(float|int $part, float|int $whole): ?float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : null;
    }

    private function normalizeTotals(array $totals, array $intKeys): array
    {
        foreach ($intKeys as $key) {
            $totals[$key] = (int) ($totals[$key] ?? 0);
        }

        return $totals;
    }

    /**
     * Rapikan tipe angka, hitung kolom persen, dan susun baris total dari penjumlahan baris.
     *
     * @param  array<string, callable>  $percentFns
     */
    private function finish(array $columns, array $rows, array $sumKeys, array $percentFns, ?string $note = null): array
    {
        $totals = array_fill_keys($sumKeys, 0);

        foreach ($rows as &$row) {
            foreach ($sumKeys as $key) {
                $row[$key] = is_float($row[$key] ?? null) ? $row[$key] : (int) ($row[$key] ?? 0);
                $totals[$key] += $row[$key];
            }
            foreach ($percentFns as $key => $fn) {
                $row[$key] = $fn($row);
            }
        }
        unset($row);

        $totalsRow = null;
        if ($rows !== []) {
            $totalsRow = $totals;
            foreach ($percentFns as $key => $fn) {
                $totalsRow[$key] = $fn($totals);
            }
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totalsRow,
            'note' => $note,
        ];
    }
}
