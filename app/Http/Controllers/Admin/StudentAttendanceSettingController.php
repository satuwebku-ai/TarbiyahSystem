<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\FingerprintNodeConnection;
use App\Models\School;
use App\Models\StudentAttendanceSetting;
use App\Services\OrganizationAccess;
use App\Services\StudentAttendanceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengaturan absensi siswa. Satu pengaturan umum (default semua sekolah, hanya pengelola tanpa
 * batas unit) dan pengaturan khusus per sekolah yang menimpanya.
 */
class StudentAttendanceSettingController extends Controller
{
    public function index(Request $request, StudentAttendanceAccess $access, OrganizationAccess $organization): View
    {
        $admin = $request->user('admin');
        $schools = $access->schools($admin)->with('unit:id,name')->get()->sortBy('unit.name')->values();
        $custom = StudentAttendanceSetting::query()->whereIn('school_id', $schools->pluck('id'))->get()->keyBy('school_id');
        $general = StudentAttendanceSetting::general();

        return view('admin.academic.student-attendance.settings.index', [
            'general' => $general,
            'generalSaved' => $general->exists,
            'canEditGeneral' => $organization->visibleUnitIds($admin) === null,
            'schools' => $schools,
            'custom' => $custom,
            'nodes' => FingerprintNodeConnection::query()->whereIn('school_id', $schools->pluck('id'))->get()->keyBy('school_id'),
            'nodeUrl' => url('/api/fingerprint-node'),
        ]);
    }

    public function editGeneral(Request $request, OrganizationAccess $organization): View
    {
        $this->authorizeGeneral($request, $organization);

        return view('admin.academic.student-attendance.settings.form', [
            'setting' => StudentAttendanceSetting::general(),
            'school' => null,
            'hasCustom' => false,
        ]);
    }

    public function updateGeneral(Request $request, OrganizationAccess $organization): RedirectResponse
    {
        $this->authorizeGeneral($request, $organization);
        $data = $this->validated($request);

        $setting = StudentAttendanceSetting::query()->whereNull('school_id')->first() ?? new StudentAttendanceSetting;
        $setting->fill($data)->save();
        AdminAuditLog::record('student_attendance.settings_updated', $request->user('admin'), $setting, ['scope' => 'general']);

        return to_route('admin.academic.student-attendance.settings.index')->with('success', 'Pengaturan umum absensi siswa disimpan.');
    }

    public function editSchool(Request $request, StudentAttendanceAccess $access, School $school): View
    {
        $school = $this->school($request, $access, $school);
        $own = StudentAttendanceSetting::query()->where('school_id', $school->id)->first();

        return view('admin.academic.student-attendance.settings.form', [
            // Sekolah yang belum punya pengaturan khusus mulai dari salinan pengaturan umum.
            'setting' => $own ?? StudentAttendanceSetting::general()->replicate(),
            'school' => $school,
            'hasCustom' => $own !== null,
        ]);
    }

    public function updateSchool(Request $request, StudentAttendanceAccess $access, School $school): RedirectResponse
    {
        $school = $this->school($request, $access, $school);
        $data = $this->validated($request);

        $setting = StudentAttendanceSetting::query()->firstOrNew(['school_id' => $school->id]);
        $setting->fill($data)->save();
        AdminAuditLog::record('student_attendance.settings_updated', $request->user('admin'), $setting, ['scope' => 'school', 'school_id' => $school->id]);

        return to_route('admin.academic.student-attendance.settings.index')->with('success', 'Pengaturan khusus sekolah disimpan.');
    }

    public function resetSchool(Request $request, StudentAttendanceAccess $access, School $school): RedirectResponse
    {
        $school = $this->school($request, $access, $school);
        $deleted = StudentAttendanceSetting::query()->where('school_id', $school->id)->delete();
        if ($deleted) {
            AdminAuditLog::record('student_attendance.settings_reset', $request->user('admin'), $school);
        }

        return to_route('admin.academic.student-attendance.settings.index')->with('success', 'Sekolah kembali memakai pengaturan umum.');
    }

    private function authorizeGeneral(Request $request, OrganizationAccess $organization): void
    {
        abort_unless($organization->visibleUnitIds($request->user('admin')) === null, 403, 'Pengaturan umum hanya dapat diubah pengelola tingkat pusat.');
    }

    private function school(Request $request, StudentAttendanceAccess $access, School $school): School
    {
        return $access->schools($request->user('admin'))->with('unit')->findOrFail($school->id);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'session_name' => ['required', 'string', 'max:60'],
            'school_days' => ['required', 'array', 'min:1'],
            'school_days.*' => ['integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'late_tolerance_minutes' => ['required', 'integer', 'between:0,180'],
            'checkin_open_time' => ['required', 'date_format:H:i'],
            'checkin_close_time' => ['required', 'date_format:H:i'],
            'auto_absent_time' => ['required', 'date_format:H:i'],
        ]);

        $data['school_days'] = collect($data['school_days'])->map(fn ($d) => (int) $d)->unique()->sort()->values()->all();
        $data['session_name'] = trim($data['session_name']);
        foreach (['fingerprint_enabled', 'skip_holidays', 'auto_absent_enabled'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $errors = [];
        if ($data['checkin_open_time'] > $data['start_time']) {
            $errors['checkin_open_time'] = 'Jam absen dibuka harus sebelum atau sama dengan jam masuk.';
        }
        if ($data['checkin_close_time'] < $data['start_time']) {
            $errors['checkin_close_time'] = 'Jam absen ditutup harus sesudah atau sama dengan jam masuk.';
        }
        if ($data['auto_absent_enabled'] && $data['auto_absent_time'] < $data['start_time']) {
            $errors['auto_absent_time'] = 'Waktu alpa otomatis harus sesudah jam masuk.';
        }
        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        return $data;
    }
}
