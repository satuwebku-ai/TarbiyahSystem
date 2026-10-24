<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AdminAuditLog;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClassPlacement;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\StudentAccount;
use App\Services\OrganizationAccess;
use App\Services\SchoolScope;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use App\Support\Dt;
use Yajra\DataTables\Facades\DataTables;

class StudentController extends Controller
{
    public function index(Request $request, OrganizationAccess $access, SchoolScope $scope): View|JsonResponse
    {
        $admin = $request->user('admin');
        $filters = $request->only(['q', 'school_id', 'status', 'komwil_id', 'komda_id']);

        // KOMWIL/KOMDA hanya mempersempit di dalam cakupan akses pengelola.
        $schoolIds = $scope->narrow(
            $scope->query($admin),
            $request->integer('komwil_id') ?: null,
            $request->integer('komda_id') ?: null,
        )->pluck('id');

        $query = Student::query()
            ->with(['school.unit', 'school.level'])
            ->whereIn('school_id', $schoolIds);

        if ($schoolId = $request->integer('school_id')) {
            $school = School::query()->findOrFail($schoolId);
            $access->assertCanAccess($admin, (int) $school->organizational_unit_id);
            $query->where('school_id', $school->id);
        }
        if ($status = $request->query('status')) {
            abort_unless(array_key_exists($status, Student::STATUSES), 422);
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('nis', 'like', "%{$search}%")
                ->orWhere('nisn', 'like', "%{$search}%"));
        }

        if (Dt::wants($request)) {
            $statuses = Student::STATUSES;

            return DataTables::eloquent($query->reorder())
                ->editColumn('name', fn (Student $s) => '<div class="fw-semibold">'.e($s->name).'</div><div class="small text-secondary">NIS '.e($s->nis).' · NISN '.e($s->nisn ?: '—').'</div>')
                ->addColumn('school_name', fn (Student $s) => e($s->school?->unit?->name ?? '—'))
                ->addColumn('level_name', fn (Student $s) => e($s->school?->level?->name ?? '—'))
                ->editColumn('status', fn (Student $s) => '<span class="badge text-bg-'.($s->status === 'active' ? 'success' : 'secondary').'">'.e($statuses[$s->status] ?? $s->status).'</span>')
                ->addColumn('action', fn (Student $s) => view('admin.academic.students._actions', ['student' => $s])->render())
                ->orderColumn('school_name', fn ($q, $dir) => $q->orderBy(
                    DB::table('schools as sc')->join('organizational_units as ou', 'ou.id', '=', 'sc.organizational_unit_id')
                        ->select('ou.name')->whereColumn('sc.id', 'students.school_id')->limit(1), $dir === 'desc' ? 'desc' : 'asc'))
                ->orderColumn('level_name', fn ($q, $dir) => $q->orderBy(
                    DB::table('schools as sc')->join('school_levels as sl', 'sl.id', '=', 'sc.school_level_id')
                        ->select('sl.name')->whereColumn('sc.id', 'students.school_id')->limit(1), $dir === 'desc' ? 'desc' : 'asc'))
                ->filterColumn('name', fn ($q, $kw) => $q->where(fn ($w) => $w->where('name', 'like', "%{$kw}%")->orWhere('nis', 'like', "%{$kw}%")->orWhere('nisn', 'like', "%{$kw}%")))
                ->filterColumn('school_name', fn ($q, $kw) => $q->whereIn('school_id', fn ($sub) => $sub->select('id')->from('schools')
                    ->whereIn('organizational_unit_id', fn ($u) => $u->select('id')->from('organizational_units')->where('name', 'like', "%{$kw}%"))))
                ->filterColumn('level_name', fn ($q, $kw) => $q->whereIn('school_id', fn ($sub) => $sub->select('id')->from('schools')
                    ->whereIn('school_level_id', fn ($l) => $l->select('id')->from('school_levels')->where('name', 'like', "%{$kw}%"))))
                ->rawColumns(['name', 'school_name', 'level_name', 'status', 'action'])
                ->make(true);
        }

        return view('admin.academic.students.index', [
            'scope' => $scope->options($admin, activeOnly: true),
            'filters' => $filters,
            'statuses' => Student::STATUSES,
        ]);
    }

    public function create(Request $request, OrganizationAccess $access): View
    {
        return view('admin.academic.students.form', [
            'student' => new Student(['status' => 'active']),
            'schools' => $this->availableSchools($request, $access),
            'accounts' => $this->availableAccounts(),
        ]);
    }

    public function store(Request $request, OrganizationAccess $access): RedirectResponse
    {
        $data = $this->validatedStudent($request);
        $school = School::query()->findOrFail($data['school_id']);
        $access->assertCanAccess($request->user('admin'), (int) $school->organizational_unit_id);

        $student = Student::query()->create($data);
        AdminAuditLog::record('student.created', $request->user('admin'), $student);

        return redirect()->route('admin.academic.students.show', $student)->with('success', 'Data siswa berhasil ditambahkan.');
    }

    public function show(Request $request, Student $student, OrganizationAccess $access): View
    {
        $this->assertStudentAccess($request, $student, $access);
        $student->load([
            'school.unit.parent',
            'school.level',
            'account',
            'guardians',
            'enrollments.academicYear',
            'enrollments.placements.classroom',
        ]);
        $availableYears = $student->school->academicYears()->orderByDesc('start_date')->get();
        $availableAccounts = $this->availableAccounts($student->student_account_id);

        return view('admin.academic.students.show', compact('student', 'availableYears', 'availableAccounts'));
    }

    public function edit(Request $request, Student $student, OrganizationAccess $access): View
    {
        $this->assertStudentAccess($request, $student, $access);
        $student->load(['school.unit', 'school.level']);

        return view('admin.academic.students.form', [
            'student' => $student,
            'schools' => collect(),
            'accounts' => $this->availableAccounts($student->student_account_id),
        ]);
    }

    public function update(Request $request, Student $student, OrganizationAccess $access): RedirectResponse
    {
        $this->assertStudentAccess($request, $student, $access);
        $student->update($this->validatedStudent($request, $student));
        AdminAuditLog::record('student.updated', $request->user('admin'), $student, [
            'changed_fields' => array_keys($student->getChanges()),
        ]);

        return redirect()->route('admin.academic.students.show', $student)->with('success', 'Data siswa diperbarui.');
    }

    public function storeGuardian(Request $request, Student $student, OrganizationAccess $access): RedirectResponse
    {
        $this->assertStudentAccess($request, $student, $access);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'relationship' => ['required', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_primary' => ['nullable', 'boolean'],
        ]);
        $guardian = DB::transaction(function () use ($student, $data, $request) {
            $primary = $request->boolean('is_primary');
            if ($primary) {
                $student->guardians()->update(['is_primary' => false]);
            }

            return $student->guardians()->create([
                ...$data,
                'is_primary' => $primary,
            ]);
        });
        AdminAuditLog::record('student.guardian.created', $request->user('admin'), $guardian);

        return back()->with('success', 'Data wali siswa ditambahkan.');
    }

    public function storeEnrollment(Request $request, Student $student, OrganizationAccess $access): RedirectResponse
    {
        $this->assertStudentAccess($request, $student, $access);
        $data = $request->validate([
            'academic_year_id' => [
                'required', 'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $student->school_id),
                Rule::unique('student_enrollments', 'academic_year_id')
                    ->where('student_id', $student->id),
            ],
            'enrollment_date' => ['required', 'date'],
            'status' => ['required', Rule::in(array_keys(StudentEnrollment::STATUSES))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $enrollment = $student->enrollments()->create($data);
        AdminAuditLog::record('student.enrollment.created', $request->user('admin'), $enrollment);

        return back()->with('success', 'Pendaftaran siswa pada tahun ajaran berhasil dicatat.');
    }

    public function updateEnrollmentStatus(
        Request $request,
        Student $student,
        StudentEnrollment $enrollment,
        OrganizationAccess $access,
    ): RedirectResponse {
        $this->assertStudentAccess($request, $student, $access);
        abort_unless((int) $enrollment->student_id === (int) $student->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(StudentEnrollment::STATUSES))],
        ]);
        DB::transaction(function () use ($enrollment, $data) {
            $enrollment->update($data);
            if ($data['status'] !== 'active') {
                $enrollment->placements()
                    ->where('status', 'active')
                    ->whereNull('end_date')
                    ->get()
                    ->each(function (StudentClassPlacement $placement) {
                        $endDate = today()->lt($placement->start_date)
                            ? $placement->start_date->toDateString()
                            : today()->toDateString();
                        $placement->update(['end_date' => $endDate, 'status' => 'ended']);
                    });
            }
        });
        AdminAuditLog::record('student.enrollment.status_changed', $request->user('admin'), $enrollment, $data);

        return back()->with('success', 'Status pendaftaran diperbarui.');
    }

    public function storePlacement(
        Request $request,
        Student $student,
        StudentEnrollment $enrollment,
        OrganizationAccess $access,
    ): RedirectResponse {
        $this->assertStudentAccess($request, $student, $access);
        abort_unless((int) $enrollment->student_id === (int) $student->id, 404);
        abort_unless($enrollment->status === 'active', 422, 'Pendaftaran yang tidak aktif tidak dapat diberi penempatan kelas.');
        $data = $request->validate([
            'classroom_id' => [
                'required', 'integer',
                Rule::exists('classrooms', 'id')
                    ->where('school_id', $student->school_id)
                    ->where('academic_year_id', $enrollment->academic_year_id)
                    ->where('status', 'active'),
            ],
            'start_date' => ['required', 'date'],
        ]);
        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $currentPlacement = $enrollment->placements()
            ->where('status', 'active')
            ->whereNull('end_date')
            ->orderByDesc('start_date')
            ->first();

        if ($currentPlacement && ! $startDate->gt($currentPlacement->start_date)) {
            throw ValidationException::withMessages([
                'start_date' => 'Tanggal penempatan baru harus setelah tanggal penempatan kelas yang masih aktif.',
            ]);
        }

        $placement = DB::transaction(function () use ($enrollment, $currentPlacement, $data, $startDate) {
            if ($currentPlacement) {
                $currentPlacement->update([
                    'end_date' => $startDate->copy()->subDay()->toDateString(),
                    'status' => 'ended',
                ]);
            }

            return $enrollment->placements()->create([
                'classroom_id' => $data['classroom_id'],
                'start_date' => $startDate->toDateString(),
                'status' => 'active',
            ]);
        });
        AdminAuditLog::record('student.class_placed', $request->user('admin'), $placement);

        return back()->with('success', 'Penempatan kelas siswa berhasil dicatat.');
    }

    private function validatedStudent(Request $request, ?Student $student = null): array
    {
        $schoolId = $student?->school_id ?? $request->input('school_id');
        $rules = [
            'nis' => [
                'required', 'string', 'max:30',
                Rule::unique('students', 'nis')
                    ->where(fn ($query) => $query->where('school_id', $schoolId))
                    ->ignore($student?->id),
            ],
            'nisn' => ['nullable', 'string', 'max:20', Rule::unique('students', 'nisn')->ignore($student?->id)],
            'fingerprint_pin' => [
                'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('students', 'fingerprint_pin')
                    ->where(fn ($query) => $query->where('school_id', $schoolId))
                    ->ignore($student?->id),
            ],
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'student_account_id' => ['nullable', 'integer', 'exists:student_accounts,id', Rule::unique('students', 'student_account_id')->ignore($student?->id)],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(array_keys(Student::STATUSES))],
        ];
        if (! $student) {
            $rules['school_id'] = ['required', 'integer', 'exists:schools,id'];
        }

        return $request->validate($rules);
    }

    private function availableSchools(Request $request, OrganizationAccess $access)
    {
        $visibleIds = $access->visibleUnitIds($request->user('admin'));

        return School::query()
            ->with(['unit:id,name,code', 'level:id,name'])
            ->where('status', 'active')
            ->when($visibleIds !== null, fn ($query) => $query->whereIn('organizational_unit_id', $visibleIds))
            ->orderBy('organizational_unit_id')
            ->get();
    }

    private function availableAccounts(?int $currentAccountId = null)
    {
        return StudentAccount::query()
            ->where(function ($query) use ($currentAccountId) {
                $query->whereDoesntHave('student');
                if ($currentAccountId) {
                    $query->orWhereKey($currentAccountId);
                }
            })
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'nis']);
    }

    private function assertStudentAccess(Request $request, Student $student, OrganizationAccess $access): void
    {
        $student->loadMissing('school');
        $access->assertCanAccess($request->user('admin'), (int) $student->school->organizational_unit_id);
    }
}
