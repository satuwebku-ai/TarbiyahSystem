<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AttendanceClock;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\FingerprintLog;
use App\Models\Student;
use App\Services\FingerprintAttendanceService;
use App\Services\StudentAttendanceAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pemetaan PIN mesin -> siswa secara massal, per sekolah (opsional per kelas). */
class FingerprintPinController extends Controller
{
    private const LIMIT = 400;

    public function index(Request $request, StudentAttendanceAccess $access): View|string
    {
        $admin = $request->user('admin');
        $schools = $access->schools($admin)->with('unit:id,name')->get()->sortBy('unit.name')->values();
        $school = $request->integer('school_id') ? $access->schools($admin)->with('unit')->findOrFail($request->integer('school_id')) : null;
        $classrooms = $school ? $access->classrooms($admin)->where('school_id', $school->id)->with('academicYear:id,name')->orderBy('name')->get() : collect();
        $classroom = $school && $request->integer('classroom_id') ? $classrooms->firstWhere('id', $request->integer('classroom_id')) : null;

        $students = collect();
        if ($school) {
            $students = $classroom
                ? $access->roster($classroom, AttendanceClock::today()->toDateString())
                : Student::query()
                    ->where('school_id', $school->id)
                    ->where('status', 'active')
                    ->when($request->filled('q'), fn (Builder $q) => $q->where(fn (Builder $w) => $w
                        ->where('name', 'like', '%'.$request->input('q').'%')
                        ->orWhere('nis', 'like', '%'.$request->input('q').'%')))
                    ->orderBy('name')
                    ->limit(self::LIMIT + 1)
                    ->get();
        }

        return view('admin.academic.fingerprint.pins', [
            'schools' => $schools,
            'school' => $school,
            'classrooms' => $classrooms,
            'classroom' => $classroom,
            'students' => $students->take(self::LIMIT),
            'truncated' => $students->count() > self::LIMIT,
            'limit' => self::LIMIT,
            'filledCount' => $students->whereNotNull('fingerprint_pin')->count(),
        ])->fragmentIf($request->ajax(), 'table');
    }

    public function update(Request $request, StudentAttendanceAccess $access, FingerprintAttendanceService $service): RedirectResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer'],
            'pins' => ['required', 'array', 'max:'.self::LIMIT],
            'pins.*' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/'],
        ], ['pins.*.regex' => 'PIN hanya boleh huruf dan angka.']);

        $admin = $request->user('admin');
        $school = $access->schools($admin)->findOrFail((int) $data['school_id']);

        $pins = collect($data['pins'])
            ->mapWithKeys(fn ($pin, $id) => [(int) $id => trim((string) $pin) === '' ? null : trim((string) $pin)]);

        $students = Student::query()->where('school_id', $school->id)->whereIn('id', $pins->keys())->get()->keyBy('id');
        abort_if($students->count() !== $pins->count(), 422, 'Ada siswa yang bukan bagian dari sekolah ini.');

        $filled = $pins->filter();
        $duplicates = $filled->duplicates();
        if ($duplicates->isNotEmpty()) {
            return back()->withErrors(['pins' => 'PIN ganda dalam daftar: '.$duplicates->unique()->implode(', ').'. Satu PIN hanya untuk satu siswa.'])->withInput();
        }

        $taken = Student::query()
            ->where('school_id', $school->id)
            ->whereIn('fingerprint_pin', $filled->values())
            ->whereNotIn('id', $pins->keys())
            ->pluck('fingerprint_pin');
        if ($taken->isNotEmpty()) {
            return back()->withErrors(['pins' => 'PIN sudah dipakai siswa lain di sekolah ini: '.$taken->implode(', ').'.'])->withInput();
        }

        $changed = 0;
        \Illuminate\Support\Facades\DB::transaction(function () use ($students, $pins, &$changed) {
            // Kosongkan dulu agar tukar PIN antar siswa tidak bentrok dengan indeks unik.
            foreach ($students as $id => $student) {
                if ($student->fingerprint_pin !== $pins[$id]) {
                    $student->forceFill(['fingerprint_pin' => null])->save();
                }
            }
            foreach ($students as $id => $student) {
                if ($pins[$id] !== null && $student->fingerprint_pin !== $pins[$id]) {
                    $student->forceFill(['fingerprint_pin' => $pins[$id]])->save();
                }
            }
            $changed = $students->filter(fn (Student $s, $id) => $s->wasChanged('fingerprint_pin'))->count();
        });

        $reprocessed = $service->reprocess(FingerprintLog::query()->where('school_id', $school->id));
        AdminAuditLog::record('fingerprint.pins_updated', $admin, $school, ['changed' => $changed, 'logs_reprocessed' => $reprocessed]);

        return back()->with('success', "Pemetaan PIN disimpan ({$changed} perubahan)".($reprocessed ? "; {$reprocessed} log lama ikut diproses." : '.'));
    }
}
