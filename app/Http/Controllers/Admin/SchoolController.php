<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AdminAuditLog;
use App\Models\Classroom;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\School;
use App\Models\SchoolLevel;
use App\Services\OrganizationAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use App\Support\Dt;
use Yajra\DataTables\Facades\DataTables;

class SchoolController extends Controller
{
    public function index(Request $request, OrganizationAccess $access): View|JsonResponse
    {
        $visibleIds = $access->visibleUnitIds($request->user('admin'));
        $canViewStudents = $request->user('admin')->can('education.students.view');
        $filters = $request->only(['q', 'status', 'level_id']);
        $query = School::query()
            ->with(['unit.parent', 'level'])
            ->withCount('academicYears')
            ->when($visibleIds !== null, fn ($q) => $q->whereIn('organizational_unit_id', $visibleIds));
        if ($canViewStudents) {
            $query->withCount('students');
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('npsn', 'like', "%{$search}%")
                    ->orWhereHas('unit', fn ($unit) => $unit->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
            });
        }
        if ($status = $request->query('status')) {
            abort_unless(array_key_exists($status, School::STATUSES), 422);
            $query->where('status', $status);
        }
        if ($levelId = $request->integer('level_id')) {
            $query->where('school_level_id', $levelId);
        }

        if (Dt::wants($request)) {
            $dt = DataTables::eloquent($query->reorder())
                ->addColumn('unit_name', fn (School $s) => '<div class="fw-semibold">'.e($s->unit?->name ?? '—').'</div>'
                    .'<div class="small text-secondary">'.e($s->unit?->code).' · NPSN '.e($s->npsn ?: 'belum diisi').'</div>')
                ->addColumn('level_name', fn (School $s) => e($s->level?->name ?? '—'))
                ->addColumn('komda_name', fn (School $s) => e($s->unit?->parent?->name ?? '—'))
                ->editColumn('status', fn (School $s) => '<span class="badge text-bg-'.($s->status === 'active' ? 'success' : 'secondary').'">'.e(School::STATUSES[$s->status] ?? $s->status).'</span>')
                ->addColumn('action', fn (School $s) => view('admin.academic.schools._actions', ['school' => $s])->render())
                ->orderColumn('unit_name', fn ($q, $dir) => Dt::orderByRelated($q, $dir, 'organizational_units', 'organizational_unit_id', 'schools'))
                ->orderColumn('level_name', fn ($q, $dir) => Dt::orderByRelated($q, $dir, 'school_levels', 'school_level_id', 'schools'))
                ->orderColumn('komda_name', fn ($q, $dir) => $q->orderBy(
                    \Illuminate\Support\Facades\DB::table('organizational_units as u')->join('organizational_units as p', 'p.id', '=', 'u.parent_id')
                        ->select('p.name')->whereColumn('u.id', 'schools.organizational_unit_id')->limit(1), $dir === 'desc' ? 'desc' : 'asc'))
                ->filterColumn('unit_name', fn ($q, $kw) => Dt::filterRelated($q, 'organizational_unit_id', 'organizational_units', ['name', 'code'], $kw))
                ->filterColumn('level_name', fn ($q, $kw) => Dt::filterRelated($q, 'school_level_id', 'school_levels', ['name'], $kw))
                ->filterColumn('komda_name', fn ($q, $kw) => $q->whereIn('organizational_unit_id', fn ($s) => $s->select('id')->from('organizational_units')
                    ->whereIn('parent_id', fn ($p) => $p->select('id')->from('organizational_units')->where('name', 'like', "%{$kw}%"))));
            if ($canViewStudents) {
                $dt->editColumn('students_count', fn (School $s) => Dt::num($s->students_count));
            }

            return $dt->rawColumns(['unit_name', 'level_name', 'komda_name', 'status', 'action'])->make(true);
        }

        return view('admin.academic.schools.index', [
            'levels' => SchoolLevel::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'filters' => $filters,
            'canViewStudents' => $canViewStudents,
        ]);
    }

    public function create(Request $request, OrganizationAccess $access): View
    {
        return view('admin.academic.schools.form', [
            'school' => new School(['status' => 'active']),
            'unit' => null,
            'levels' => SchoolLevel::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'parentUnits' => $this->availableKomdaUnits($request, $access),
        ]);
    }

    public function store(Request $request, OrganizationAccess $access): RedirectResponse
    {
        $data = $this->validatedSchool($request);
        $parent = OrganizationalUnit::query()->with('type')->findOrFail($data['parent_unit_id']);
        abort_unless($parent->type?->slug === 'komda' && $parent->status === 'active', 422, 'Sekolah harus berada di bawah KOMDA yang aktif.');
        $access->assertCanAccess($request->user('admin'), (int) $parent->id);

        $unitType = OrganizationalUnitType::query()->where('slug', 'sekolah')->firstOrFail();
        $school = DB::transaction(function () use ($data, $parent, $unitType) {
            $unit = OrganizationalUnit::query()->create([
                'type_id' => $unitType->id,
                'parent_id' => $parent->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'status' => $data['status'],
            ]);

            return School::query()->create([
                'organizational_unit_id' => $unit->id,
                'school_level_id' => $data['school_level_id'],
                'npsn' => $data['npsn'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'status' => $data['status'],
            ]);
        });
        AdminAuditLog::record('school.created', $request->user('admin'), $school);

        return redirect()->route('admin.academic.schools.show', $school)->with('success', 'Sekolah berhasil ditambahkan di bawah KOMDA.');
    }

    public function show(Request $request, School $school, OrganizationAccess $access): View
    {
        $this->assertSchoolAccess($request, $school, $access);
        $school->load(['unit.parent.parent', 'level']);
        $canViewStudents = $request->user('admin')->can('education.students.view');
        $yearQuery = $school->academicYears();
        $classroomQuery = fn ($query) => $query->orderBy('name');
        if ($canViewStudents) {
            $school->loadCount('students');
            $yearQuery->withCount('enrollments');
            $classroomQuery = fn ($query) => $query->withCount('placements')->orderBy('name');
            $students = $school->students()->with('account')->orderBy('name')->limit(10)->get();
        } else {
            $students = collect();
        }
        $years = $yearQuery->with(['classrooms' => $classroomQuery])->get();

        return view('admin.academic.schools.show', compact('school', 'years', 'students', 'canViewStudents'));
    }

    public function edit(Request $request, School $school, OrganizationAccess $access): View
    {
        $this->assertSchoolAccess($request, $school, $access);
        $school->load(['unit.parent', 'level']);

        return view('admin.academic.schools.form', [
            'school' => $school,
            'unit' => $school->unit,
            'levels' => SchoolLevel::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'parentUnits' => collect(),
        ]);
    }

    public function update(Request $request, School $school, OrganizationAccess $access): RedirectResponse
    {
        $this->assertSchoolAccess($request, $school, $access);
        $data = $this->validatedSchool($request, $school);

        DB::transaction(function () use ($school, $data) {
            $school->update([
                'school_level_id' => $data['school_level_id'],
                'npsn' => $data['npsn'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'status' => $data['status'],
            ]);
            $school->unit()->update([
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'status' => $data['status'],
            ]);
        });
        AdminAuditLog::record('school.updated', $request->user('admin'), $school, [
            'changed_fields' => array_keys($school->getChanges()),
        ]);

        return redirect()->route('admin.academic.schools.show', $school)->with('success', 'Data sekolah diperbarui.');
    }

    public function storeAcademicYear(Request $request, School $school, OrganizationAccess $access): RedirectResponse
    {
        $this->assertSchoolAccess($request, $school, $access);
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:30',
                Rule::unique('academic_years', 'name')->where('school_id', $school->id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $isActive = $request->boolean('is_active');

        $year = DB::transaction(function () use ($school, $data, $isActive) {
            if ($isActive) {
                $school->academicYears()->update(['is_active' => false]);
            }

            return $school->academicYears()->create([
                ...$data,
                'is_active' => $isActive,
            ]);
        });
        AdminAuditLog::record('academic_year.created', $request->user('admin'), $year);

        return back()->with('success', 'Tahun ajaran ditambahkan.');
    }

    public function activateAcademicYear(Request $request, School $school, AcademicYear $academicYear, OrganizationAccess $access): RedirectResponse
    {
        $this->assertSchoolAccess($request, $school, $access);
        abort_unless((int) $academicYear->school_id === (int) $school->id, 404);

        DB::transaction(function () use ($school, $academicYear) {
            $school->academicYears()->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);
        });
        AdminAuditLog::record('academic_year.activated', $request->user('admin'), $academicYear);

        return back()->with('success', "Tahun ajaran {$academicYear->name} diaktifkan.");
    }

    public function storeClassroom(Request $request, School $school, OrganizationAccess $access): RedirectResponse
    {
        $this->assertSchoolAccess($request, $school, $access);
        $data = $request->validate([
            'academic_year_id' => [
                'required', 'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $school->id),
            ],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('classrooms', 'name')->where('academic_year_id', $request->input('academic_year_id')),
            ],
            'grade_label' => ['nullable', 'string', 'max:40'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ]);
        $classroom = Classroom::query()->create([
            ...$data,
            'school_id' => $school->id,
            'status' => 'active',
        ]);
        AdminAuditLog::record('classroom.created', $request->user('admin'), $classroom);

        return back()->with('success', 'Kelas berhasil ditambahkan.');
    }

    private function validatedSchool(Request $request, ?School $school = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'code' => [
                $school ? 'nullable' : 'required',
                'string', 'max:40',
                Rule::unique('organizational_units', 'code')->ignore($school?->organizational_unit_id),
            ],
            'parent_unit_id' => [$school ? 'nullable' : 'required', 'integer', 'exists:organizational_units,id'],
            'school_level_id' => ['required', 'integer', Rule::exists('school_levels', 'id')->where('is_active', true)],
            'npsn' => ['nullable', 'string', 'max:20', Rule::unique('schools', 'npsn')->ignore($school?->id)],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'status' => ['required', Rule::in(array_keys(School::STATUSES))],
        ]);

        if ($school) {
            $data['code'] = $school->unit->code;
            $data['parent_unit_id'] = $school->unit->parent_id;
        }

        return $data;
    }

    private function availableKomdaUnits(Request $request, OrganizationAccess $access)
    {
        $visibleIds = $access->visibleUnitIds($request->user('admin'));

        return OrganizationalUnit::query()
            ->where('status', 'active')
            ->whereHas('type', fn ($query) => $query->where('slug', 'komda'))
            ->when($visibleIds !== null, fn ($query) => $query->whereIn('id', $visibleIds))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function assertSchoolAccess(Request $request, School $school, OrganizationAccess $access): void
    {
        $access->assertCanAccess($request->user('admin'), (int) $school->organizational_unit_id);
    }
}
