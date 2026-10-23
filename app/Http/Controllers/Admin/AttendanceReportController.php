<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AttendanceClock;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\AttendanceRecapService;
use App\Services\SchoolScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceReportController extends Controller
{
    private const LIMIT = 500;

    public function daily(Request $request, AttendanceRecapService $recap, SchoolScope $schools): View|string
    {
        $date = $this->parseDate((string) $request->query('date')) ?? AttendanceClock::today();

        return view('admin.attendance.daily', $this->report($request, $date->copy(), $date->copy(), $recap, $schools) + ['date' => $date])
            ->fragmentIf($request->ajax(), 'table');
    }

    public function monthly(Request $request, AttendanceRecapService $recap, SchoolScope $schools): View|string
    {
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? Carbon::parse($request->query('month').'-01', AttendanceClock::tz())->startOfDay()
            : AttendanceClock::today()->startOfMonth();

        return view('admin.attendance.monthly', $this->report($request, $month->copy(), $month->copy()->endOfMonth()->startOfDay(), $recap, $schools) + ['month' => $month])
            ->fragmentIf($request->ajax(), 'table');
    }

    private function report(Request $request, Carbon $from, Carbon $to, AttendanceRecapService $recap, SchoolScope $schools): array
    {
        $admin = $request->user('admin');

        // KOMWIL → KOMDA → Sekolah hanya mempersempit di dalam cakupan akses pengelola.
        $filters = $schools->filters($request);
        $scope = $schools->unitScope($admin, $filters, $request->integer('unit_id') ?: null);

        $employees = Employee::query()
            ->whereHas('assignments', fn ($a) => $a
                ->where('start_date', '<=', $to->toDateString())
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $from->toDateString()))
                ->when($scope !== null, fn ($q) => $q->whereIn('organizational_unit_id', $scope)))
            ->orderBy('name')
            ->limit(self::LIMIT + 1)
            ->get(['id', 'name', 'employee_number', 'status']);

        $truncated = $employees->count() > self::LIMIT;

        return $recap->build($from, $to, $employees->take(self::LIMIT), $scope) + [
            'scope' => $schools->options($admin),
            'filters' => $filters,
            'truncated' => $truncated,
        ];
    }

    private function parseDate(string $value): ?Carbon
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))
            ? Carbon::parse($value, AttendanceClock::tz())->startOfDay()
            : null;
    }
}
