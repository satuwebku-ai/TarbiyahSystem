<?php

namespace App\Http\Controllers\Pegawai;

use App\Enums\EmployeeStatus;
use App\Helpers\AttendanceClock;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OrganizationalUnit;
use App\Services\AttendancePolicyResolver;
use App\Services\AttendanceService;
use App\Services\PegawaiPortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

/**
 * Absen & koreksi milik pegawai yang sedang login. Pegawai ditentukan dari akun (bukan dari input),
 * jadi tidak ada cara menyentuh data pegawai lain lewat portal ini.
 */
class AttendanceController extends Controller
{
    public function show(Request $request, PegawaiPortalAccess $access, AttendancePolicyResolver $resolver): View
    {
        $employee = $this->employee($request, $access);
        $now = AttendanceClock::now();
        $unitId = $resolver->unitFor($employee, $now);
        $policy = $unitId ? $resolver->policyForUnit($unitId) : null;
        $holiday = $unitId ? $resolver->holidayFor($unitId, $now->toDateString()) : null;
        $today = AttendanceRecord::query()->where('employee_id', $employee->id)->where('attendance_date', $now->toDateString())->first();

        return view('pegawai.dashboard', [
            'employee' => $employee,
            'now' => $now,
            'unit' => $unitId ? OrganizationalUnit::query()->whereKey($unitId)->value('name') : null,
            'policy' => $policy,
            'holiday' => $holiday,
            'today' => $today,
            'needsPhoto' => (bool) $policy?->require_photo,
            'needsLocation' => (bool) $policy?->require_location,
            'canIn' => ! $today && $policy && ! $holiday
                && $policy->isWorkDay($now->isoWeekday()) && $employee->status === EmployeeStatus::Active,
            'canOut' => $today && $today->check_in_at && ! $today->check_out_at,
            'recent' => AttendanceRecord::query()->where('employee_id', $employee->id)->orderByDesc('attendance_date')->limit(7)->get(),
            'corrections' => AttendanceCorrection::query()->where('employee_id', $employee->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function checkIn(Request $request, PegawaiPortalAccess $access, AttendanceService $service): RedirectResponse
{
    try {
        $record = $service->checkIn($this->employee($request, $access), $request->ip(), $this->proof($request));
    } catch (ValidationException $e) {
        throw $e->redirectTo(route('pegawai.dashboard'));
    }

    return back()->with('success', 'Check-in tercatat pukul '.AttendanceClock::toLocal($record->check_in_at)->format('H:i').'.');
}

    public function checkOut(Request $request, PegawaiPortalAccess $access, AttendanceService $service): RedirectResponse
    {
        $record = $service->checkOut($this->employee($request, $access), $request->ip(), $this->proof($request));

        return back()->with('success', 'Check-out tercatat pukul '.AttendanceClock::toLocal($record->check_out_at)->format('H:i').'.');
    }

    public function storeCorrection(Request $request, PegawaiPortalAccess $access, AttendanceService $service): RedirectResponse
    {
        $data = $request->validate([
            'attendance_date' => ['required', 'date'],
            'requested_check_in' => ['nullable', 'date_format:H:i'],
            'requested_check_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $service->submitCorrection($this->employee($request, $access), $request->user('pegawai'), $data);

        return back()->with('success', 'Pengajuan koreksi dikirim dan menunggu persetujuan.');
    }

    /** Bukti absen dari perangkat. Ada tidaknya dan kelayakannya ditentukan kebijakan di AttendanceService. */
    private function proof(Request $request): array
    {
        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        return [...$data, 'photo' => $request->file('photo')];
    }

    /** Middleware sudah menjamin ada; abort 403 hanya sabuk pengaman bila route dipakai tanpa middleware itu. */
    private function employee(Request $request, PegawaiPortalAccess $access): Employee
    {
        return $access->employeeFor($request->user('pegawai')) ?? abort(403, 'Akun belum dihubungkan ke data pegawai.');
    }
}
