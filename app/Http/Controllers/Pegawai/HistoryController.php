<?php

namespace App\Http\Controllers\Pegawai;

use App\Helpers\AttendanceClock;
use App\Http\Controllers\Controller;
use App\Services\AttendanceRecapService;
use App\Services\PegawaiPortalAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Riwayat absensi bulanan milik sendiri; angka sama dengan rekap pengelola (satu sumber: AttendanceRecapService). */
class HistoryController extends Controller
{
    public function __invoke(Request $request, PegawaiPortalAccess $access, AttendanceRecapService $recap): View
    {
        $employee = $access->employeeFor($request->user('pegawai')) ?? abort(403);

        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $thisMonth = AttendanceClock::today()->startOfMonth();
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m-d', $request->string('month').'-01', AttendanceClock::tz())->startOfDay()
            : $thisMonth;
        if ($month->gt($thisMonth)) {
            $month = $thisMonth;
        }

        $result = $recap->build($month->copy()->startOfMonth(), $month->copy()->endOfMonth()->startOfDay(), collect([$employee]));

        return view('pegawai.history', [
            'employee' => $employee,
            'month' => $month,
            'maxMonth' => $thisMonth,
            'row' => $result['rows']->first(),
        ]);
    }
}
