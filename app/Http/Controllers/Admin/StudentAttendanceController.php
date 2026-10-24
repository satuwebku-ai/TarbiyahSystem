<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StudentAttendanceStatus;
use App\Helpers\AttendanceClock;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AdminAuditLog;
use App\Models\StudentAttendanceCorrection;
use App\Models\StudentAttendanceRecord;
use App\Models\StudentAttendanceSession;
use App\Models\StudentAttendanceSetting;
use App\Services\OrganizationAccess;
use App\Services\SchoolScope;
use App\Services\StudentAttendanceAccess;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentAttendanceController extends Controller
{
    public function index(Request $request, StudentAttendanceAccess $access, SchoolScope $scope): View
    {
        $admin = $request->user('admin');
        $school = $request->integer('school_id')
            ? $access->schools($admin)->with('unit')->findOrFail($request->integer('school_id'))
            : null;
        $classroom = $request->integer('classroom_id')
            ? $access->findClassroom($admin, $request->integer('classroom_id'))
            : null;

        if ($classroom && $school && $classroom->school_id !== $school->id) {
            abort(422, 'Kelas yang dipilih bukan bagian dari sekolah tersebut.');
        }
        $school ??= $classroom?->school;

        $date = $this->parseDate((string) $request->query('date')) ?? AttendanceClock::today();
        $sessionName = trim((string) $request->query('session_name', ''))
            ?: ($school ? StudentAttendanceSetting::forSchool($school->id)->session_name : 'Harian');
        $dateWithinYear = $classroom
            ? $this->dateIsWithinAcademicYear($classroom->academicYear, $date)
            : false;
        $roster = $classroom && $dateWithinYear
            ? $access->roster($classroom, $date->toDateString())
            : collect();

        $session = $classroom && $dateWithinYear
            ? StudentAttendanceSession::query()
                ->where('classroom_id', $classroom->id)
                ->whereDate('attendance_date', $date->toDateString())
                ->where('name', $sessionName)
                ->with(['records.student', 'records.corrections.changedBy'])
                ->first()
            : null;
        $records = $session?->records->keyBy('student_id') ?? collect();
        $corrections = $session?->records
            ->flatMap(fn ($record) => $record->corrections->map(fn ($correction) => [
                'student' => $record->student,
                'correction' => $correction,
            ]))
            ->sortByDesc(fn ($entry) => $entry['correction']->created_at)
            ->values() ?? collect();

        $schoolOptions = $scope->options($admin);
        $classrooms = $access->classrooms($admin)
            ->with(['school.unit:id,name', 'academicYear:id,name'])
            ->orderBy('school_id')
            ->orderBy('name')
            ->get();

        return view('admin.academic.student-attendance.index', [
            'scope' => $schoolOptions,
            'classrooms' => $classrooms,
            'selectedSchool' => $school,
            'selectedClassroom' => $classroom,
            'date' => $date,
            'sessionName' => $sessionName,
            'dateWithinYear' => $dateWithinYear,
            'roster' => $roster,
            'records' => $records,
            'corrections' => $corrections,
            'statuses' => StudentAttendanceStatus::cases(),
        ]);
    }

    public function store(Request $request, StudentAttendanceAccess $access): RedirectResponse
    {
        $statusValues = array_map(
            fn (StudentAttendanceStatus $status) => $status->value,
            StudentAttendanceStatus::cases()
        );
        $data = $request->validate([
            'school_id' => ['required', 'integer'],
            'classroom_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'session_name' => ['required', 'string', 'max:60'],
            'attendance' => ['required', 'array', 'min:1'],
            'attendance.*.status' => ['required', Rule::in($statusValues)],
            'attendance.*.notes' => ['nullable', 'string', 'max:500'],
            'correction_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $admin = $request->user('admin');
        $school = $access->schools($admin)->findOrFail((int) $data['school_id']);
        $classroom = $access->findClassroom($admin, (int) $data['classroom_id']);
        abort_unless($classroom->school_id === $school->id, 422, 'Kelas yang dipilih bukan bagian dari sekolah tersebut.');

        $date = Carbon::createFromFormat('!Y-m-d', $data['date'], AttendanceClock::tz());
        if (! $this->dateIsWithinAcademicYear($classroom->academicYear, $date)) {
            return back()
                ->withErrors(['date' => 'Tanggal absensi harus berada dalam periode tahun ajaran kelas.'])
                ->withInput();
        }

        $roster = $access->roster($classroom, $date->toDateString());
        if ($roster->isEmpty()) {
            return back()
                ->withErrors(['attendance' => 'Tidak ada siswa aktif yang terdaftar di kelas pada tanggal tersebut.'])
                ->withInput();
        }

        $rosterIds = $roster->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $submittedIds = collect(array_keys($data['attendance']))
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
        if ($rosterIds !== $submittedIds) {
            return back()
                ->withErrors(['attendance' => 'Daftar siswa berubah atau tidak sesuai dengan kelas. Muat ulang halaman lalu coba lagi.'])
                ->withInput();
        }

        $sessionName = trim($data['session_name']);
        if ($sessionName === '') {
            return back()->withErrors(['session_name' => 'Nama sesi wajib diisi.'])->withInput();
        }

        [$session, $correctionCount] = DB::transaction(function () use ($classroom, $date, $sessionName, $data, $admin) {
            $session = StudentAttendanceSession::query()->firstOrCreate(
                [
                    'classroom_id' => $classroom->id,
                    'attendance_date' => $date->toDateString(),
                    'name' => $sessionName,
                ],
                ['recorded_by_admin_id' => $admin->id]
            );
            $session->update(['recorded_by_admin_id' => $admin->id]);
            $correctionReason = trim((string) ($data['correction_reason'] ?? ''));
            $correctionCount = 0;

            foreach ($data['attendance'] as $studentId => $entry) {
                $record = StudentAttendanceRecord::query()->firstOrNew(
                    [
                        'student_attendance_session_id' => $session->id,
                        'student_id' => (int) $studentId,
                    ]
                );
                $newNotes = $entry['notes'] ?? null;
                $manualEdit = ! $record->exists;

                if ($record->exists) {
                    $previousStatus = $record->status->value;
                    $previousNotes = $record->notes;
                    $changed = $previousStatus !== $entry['status'] || $previousNotes !== $newNotes;

                    if ($changed && $correctionReason === '') {
                        throw ValidationException::withMessages([
                            'correction_reason' => 'Alasan koreksi wajib diisi saat mengubah absensi yang sudah tersimpan.',
                        ]);
                    }

                    if ($changed) {
                        StudentAttendanceCorrection::query()->create([
                            'student_attendance_record_id' => $record->id,
                            'previous_status' => $previousStatus,
                            'new_status' => $entry['status'],
                            'previous_notes' => $previousNotes,
                            'new_notes' => $newNotes,
                            'reason' => $correctionReason,
                            'changed_by_admin_id' => $admin->id,
                        ]);
                        $correctionCount++;
                        $manualEdit = true;
                    }
                }

                $record->fill([
                    'status' => $entry['status'],
                    'notes' => $newNotes,
                    'recorded_by_admin_id' => $admin->id,
                ] + ($manualEdit ? ['source' => 'manual'] : []))->save();
            }

            return [$session, $correctionCount];
        });

        AdminAuditLog::record('student_attendance.saved', $admin, $session, [
            'classroom_id' => $classroom->id,
            'attendance_date' => $date->toDateString(),
            'session_name' => $sessionName,
            'student_count' => count($data['attendance']),
            'correction_count' => $correctionCount,
        ]);

        return to_route('admin.academic.student-attendance.index', [
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'date' => $date->toDateString(),
            'session_name' => $sessionName,
        ])->with('success', $correctionCount > 0
            ? "Absensi diperbarui; {$correctionCount} koreksi dicatat."
            : 'Absensi siswa berhasil disimpan.');
    }

    public function report(
        Request $request,
        StudentAttendanceAccess $access,
        OrganizationAccess $organization,
        SchoolScope $scope
    ): View|string {
        $admin = $request->user('admin');
        $period = in_array($request->query('period'), ['month', 'year'], true)
            ? $request->query('period')
            : 'day';
        $date = $this->parseDate((string) $request->query('date')) ?? AttendanceClock::today();
        $month = $this->parseMonth((string) $request->query('month')) ?? $date->copy()->startOfMonth();

        $school = $request->integer('school_id')
            ? $access->schools($admin)->with('unit')->findOrFail($request->integer('school_id'))
            : null;
        $academicYear = $request->integer('academic_year_id')
            ? $access->academicYears($admin)->findOrFail($request->integer('academic_year_id'))
            : null;
        $classroom = $request->integer('classroom_id')
            ? $access->findClassroom($admin, $request->integer('classroom_id'))
            : null;

        if ($academicYear && $school && $academicYear->school_id !== $school->id) {
            abort(422, 'Tahun ajaran yang dipilih bukan milik sekolah tersebut.');
        }
        if ($classroom && $school && $classroom->school_id !== $school->id) {
            abort(422, 'Kelas yang dipilih bukan bagian dari sekolah tersebut.');
        }
        if ($classroom && $academicYear && $classroom->academic_year_id !== $academicYear->id) {
            abort(422, 'Kelas yang dipilih bukan bagian dari tahun ajaran tersebut.');
        }

        $from = match ($period) {
            'month' => $month->copy()->startOfMonth(),
            'year' => $academicYear?->start_date?->copy() ?? $date->copy(),
            default => $date->copy(),
        };
        $to = match ($period) {
            'month' => $month->copy()->endOfMonth(),
            'year' => $academicYear?->end_date?->copy() ?? $date->copy(),
            default => $date->copy(),
        };

        $komwilId = $request->integer('komwil_id') ?: null;
        $komdaId = $request->integer('komda_id') ?: null;
        $schoolOptions = $scope->options($admin);
        $years = $access->academicYears($admin)
            ->with('school.unit:id,name')
            ->orderByDesc('start_date')
            ->get();
        $classrooms = $access->classrooms($admin)
            ->with(['school.unit:id,name', 'academicYear:id,name'])
            ->orderBy('name')
            ->get();

        $baseQuery = DB::table('student_attendance_records as records')
            ->join('student_attendance_sessions as sessions', 'sessions.id', '=', 'records.student_attendance_session_id')
            ->join('classrooms', 'classrooms.id', '=', 'sessions.classroom_id')
            ->join('schools', 'schools.id', '=', 'classrooms.school_id')
            ->join('organizational_units as units', 'units.id', '=', 'schools.organizational_unit_id')
            ->join('academic_years', 'academic_years.id', '=', 'classrooms.academic_year_id')
            ->join('students', 'students.id', '=', 'records.student_id')
            ->whereBetween('sessions.attendance_date', [$from->toDateString(), $to->toDateString()])
            ->when($period === 'year' && ! $academicYear, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($school, fn ($query) => $query->where('schools.id', $school->id))
            ->when($academicYear, fn ($query) => $query->where('academic_years.id', $academicYear->id))
            ->when($classroom, fn ($query) => $query->where('classrooms.id', $classroom->id));

        $visibleUnits = $organization->visibleUnitIds($admin);
        if ($visibleUnits !== null) {
            $baseQuery->whereIn('schools.organizational_unit_id', $visibleUnits);
        }
        if ($komwilId) {
            $baseQuery->whereIn('schools.organizational_unit_id', $organization->withDescendants([$komwilId]));
        }
        if ($komdaId) {
            $baseQuery->whereIn('schools.organizational_unit_id', $organization->withDescendants([$komdaId]));
        }

        $counts = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw('COUNT(DISTINCT sessions.id) as total_sessions')
            ->selectRaw("SUM(CASE WHEN records.status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN records.status = 'late' THEN 1 ELSE 0 END) as late")
            ->selectRaw("SUM(CASE WHEN records.status = 'permit' THEN 1 ELSE 0 END) as permit")
            ->selectRaw("SUM(CASE WHEN records.status = 'sick' THEN 1 ELSE 0 END) as sick")
            ->selectRaw("SUM(CASE WHEN records.status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->first();

        $rows = (clone $baseQuery)
            ->select([
                'schools.id as school_id',
                'units.name as school_name',
                'academic_years.name as academic_year_name',
                'classrooms.id as classroom_id',
                'classrooms.name as classroom_name',
                'students.id as student_id',
                'students.nis',
                'students.name as student_name',
            ])
            ->selectRaw('COUNT(*) as total_sessions')
            ->selectRaw("SUM(CASE WHEN records.status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN records.status = 'late' THEN 1 ELSE 0 END) as late")
            ->selectRaw("SUM(CASE WHEN records.status = 'permit' THEN 1 ELSE 0 END) as permit")
            ->selectRaw("SUM(CASE WHEN records.status = 'sick' THEN 1 ELSE 0 END) as sick")
            ->selectRaw("SUM(CASE WHEN records.status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->groupBy(
                'schools.id',
                'units.name',
                'academic_years.name',
                'classrooms.id',
                'classrooms.name',
                'students.id',
                'students.nis',
                'students.name'
            )
            ->orderBy('units.name')
            ->orderBy('classrooms.name')
            ->orderBy('students.name')
            ->paginate(100)
            ->withQueryString();

        return view('admin.academic.student-attendance.report', [
            'scope' => $schoolOptions,
            'years' => $years,
            'classrooms' => $classrooms,
            'selectedSchool' => $school,
            'selectedKomwil' => $komwilId,
            'selectedKomda' => $komdaId,
            'selectedAcademicYear' => $academicYear,
            'selectedClassroom' => $classroom,
            'period' => $period,
            'date' => $date,
            'month' => $month,
            'from' => $from,
            'to' => $to,
            'counts' => $counts,
            'rows' => $rows,
        ])->fragmentIf($request->ajax(), 'table');
    }

    private function parseDate(string $value): ?Carbon
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
            && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))
                ? Carbon::parse($value, AttendanceClock::tz())->startOfDay()
                : null;
    }

    private function parseMonth(string $value): ?Carbon
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)
            && checkdate((int) substr($value, 5, 2), 1, (int) substr($value, 0, 4))
                ? Carbon::parse($value.'-01', AttendanceClock::tz())->startOfDay()
                : null;
    }

    private function dateIsWithinAcademicYear(AcademicYear $year, Carbon $date): bool
    {
        return $date->toDateString() >= $year->start_date->toDateString()
            && $date->toDateString() <= $year->end_date->toDateString();
    }
}
