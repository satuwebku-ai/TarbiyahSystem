<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AttendanceClock;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\OrganizationalUnit;
use App\Services\EmployeeAccess;
use App\Services\OrganizationAccess;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    public function __construct(
        private ReportService $reports,
        private OrganizationAccess $org,
        private EmployeeAccess $access,
    ) {}

    /** Dashboard: ringkasan semua laporan yang boleh dilihat pengguna. */
    public function index(Request $request): View
    {
        $admin = $request->user('admin');
        [$from, $to, $unitId, $scope] = $this->context($request, $admin);

        $summaries = [];
        foreach ($this->allowedTypes($admin) as $type => $definition) {
            $summaries[$type] = $this->reports->build($type, $scope, $from, $to) + ['definition' => $definition];
        }

        return view('admin.reports.index', [
            'summaries' => $summaries,
            'types' => $this->allowedTypes($admin),
            'from' => $from,
            'to' => $to,
            'unitId' => $unitId,
            'units' => $this->access->availableUnits($admin),
        ]);
    }

    public function show(Request $request, string $report): View
    {
        $admin = $request->user('admin');
        $definition = $this->authorizeType($admin, $report);
        [$from, $to, $unitId, $scope] = $this->context($request, $admin);

        return view('admin.reports.show', [
            'type' => $report,
            'definition' => $definition,
            'data' => $this->reports->build($report, $scope, $from, $to),
            'from' => $from,
            'to' => $to,
            'unitId' => $unitId,
            'units' => $this->access->availableUnits($admin),
        ]);
    }

    /** Tampilan siap cetak; simpan sebagai PDF lewat dialog cetak browser. */
    public function printView(Request $request, string $report): View
    {
        $admin = $request->user('admin');
        $definition = $this->authorizeType($admin, $report);
        [$from, $to, $unitId, $scope] = $this->context($request, $admin);

        AdminAuditLog::record('report.printed', $admin, null, $this->auditDetails($report, $from, $to, $unitId));

        return view('admin.reports.print', [
            'definition' => $definition,
            'data' => $this->reports->build($report, $scope, $from, $to),
            'from' => $from,
            'to' => $to,
            'unitName' => $unitId ? OrganizationalUnit::query()->whereKey($unitId)->value('name') : null,
            'admin' => $admin,
        ]);
    }

    /** CSV berpemisah titik koma dengan BOM UTF-8 agar langsung terbaca di Excel (locale Indonesia). */
    public function export(Request $request, string $report): StreamedResponse
    {
        $admin = $request->user('admin');
        $definition = $this->authorizeType($admin, $report);
        [$from, $to, $unitId, $scope] = $this->context($request, $admin);
        $data = $this->reports->build($report, $scope, $from, $to);

        AdminAuditLog::record('report.exported', $admin, null, $this->auditDetails($report, $from, $to, $unitId));

        $unitName = $unitId ? OrganizationalUnit::query()->whereKey($unitId)->value('name') : 'Semua unit dalam cakupan';
        $filename = sprintf('laporan-%s-%s_%s.csv', $report, $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($definition, $data, $from, $to, $unitName) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $put = fn (array $cells) => fputcsv($out, array_map([$this, 'safeCell'], $cells), ';');

            $put([$definition['title']]);
            if ($definition['uses_period']) {
                $put(['Periode', $from->toDateString().' s.d. '.$to->toDateString()]);
            }
            $put(['Unit', $unitName]);
            $put(['Dibuat', AttendanceClock::now()->format('Y-m-d H:i')]);
            $put([]);
            $put(array_column($data['columns'], 'label'));

            foreach ($data['rows'] as $row) {
                $put($this->csvRow($data['columns'], $row));
            }
            if ($data['totals']) {
                $put($this->csvRow($data['columns'], ['unit' => 'Total', 'school' => 'Total', 'program' => 'Total'] + $data['totals']));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------

    /** @return array{0: Carbon, 1: Carbon, 2: ?int, 3: ?array} */
    private function context(Request $request, Admin $admin): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'unit_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $tz = AttendanceClock::tz();
        $today = AttendanceClock::today();
        $from = ! empty($data['from']) ? Carbon::createFromFormat('!Y-m-d', $data['from'], $tz) : $today->copy()->startOfMonth();
        $to = ! empty($data['to']) ? Carbon::createFromFormat('!Y-m-d', $data['to'], $tz) : $today->copy();

        if ($to->lt($from)) {
            throw ValidationException::withMessages(['to' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);
        }
        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => 'Rentang periode maksimal '.self::MAX_RANGE_DAYS.' hari.']);
        }

        $unitId = ! empty($data['unit_id']) ? (int) $data['unit_id'] : null;
        if ($unitId) {
            OrganizationalUnit::query()->findOrFail($unitId);
            $this->org->assertCanAccess($admin, $unitId);
        }

        return [$from, $to, $unitId, $this->reports->resolveScope($admin, $unitId)];
    }

    /** Laporan yang boleh dilihat: harus punya izin modul asalnya. */
    private function allowedTypes(Admin $admin): array
    {
        return array_filter(
            ReportService::TYPES,
            fn (array $definition) => Gate::forUser($admin)->allows($definition['can'])
        );
    }

    private function authorizeType(Admin $admin, string $type): array
    {
        $definition = $this->reports->definition($type);
        abort_unless(Gate::forUser($admin)->allows($definition['can']), 403);

        return $definition;
    }

    private function auditDetails(string $report, Carbon $from, Carbon $to, ?int $unitId): array
    {
        return [
            'report' => $report,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'unit_id' => $unitId,
        ];
    }

    private function csvRow(array $columns, array $row): array
    {
        return array_map(function (array $column) use ($row) {
            $value = $row[$column['key']] ?? null;

            return match (true) {
                $value === null => '',
                $column['type'] === 'money' => number_format((float) $value, 2, '.', ''),
                $column['type'] === 'percent' => number_format((float) $value, 1, '.', ''),
                default => $value,
            };
        }, $columns);
    }

    /** Cegah injeksi rumus spreadsheet pada sel teks yang berasal dari data pengguna. */
    public function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && ! is_numeric($value) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
