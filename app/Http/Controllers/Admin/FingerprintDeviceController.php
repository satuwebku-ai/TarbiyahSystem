<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\FingerprintDevice;
use App\Models\FingerprintLog;
use App\Services\FingerprintAttendanceService;
use App\Services\StudentAttendanceAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FingerprintDeviceController extends Controller
{
    public function index(Request $request, StudentAttendanceAccess $access): View
    {
        $admin = $request->user('admin');

        $devices = $this->scoped($request, $access)
            ->with('school.unit:id,name')
            ->withCount(['logs as unmapped_count' => fn (Builder $q) => $q->where('result', 'unmapped')])
            ->orderByRaw('school_id is null desc')
            ->orderBy('name')
            ->get();

        return view('admin.academic.fingerprint.devices.index', [
            'devices' => $devices,
            'pushUrl' => url('/iclock/cdata'),
            'canManage' => $admin->can('education.fingerprint.manage'),
        ]);
    }

    public function create(Request $request, StudentAttendanceAccess $access): View
    {
        return view('admin.academic.fingerprint.devices.form', [
            'device' => new FingerprintDevice(['is_active' => true]),
            'schools' => $this->schoolOptions($request, $access),
        ]);
    }

    public function store(Request $request, StudentAttendanceAccess $access): RedirectResponse
    {
        $data = $request->validate([
            'serial_number' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:fingerprint_devices,serial_number'],
            ...$this->rules(),
        ]);
        $data = $this->prepare($request, $access, $data);

        $device = FingerprintDevice::query()->create($data);
        AdminAuditLog::record('fingerprint.device_created', $request->user('admin'), $device, [
            'serial_number' => $device->serial_number,
            'school_id' => $device->school_id,
        ]);

        return to_route('admin.academic.fingerprint.devices.index')->with('success', 'Mesin fingerprint ditambahkan.');
    }

    public function edit(Request $request, StudentAttendanceAccess $access, FingerprintDevice $device): View
    {
        $this->authorizeDevice($request, $access, $device);

        return view('admin.academic.fingerprint.devices.form', [
            'device' => $device,
            'schools' => $this->schoolOptions($request, $access),
        ]);
    }

    public function update(Request $request, StudentAttendanceAccess $access, FingerprintDevice $device, FingerprintAttendanceService $service): RedirectResponse
    {
        $this->authorizeDevice($request, $access, $device);
        $wasInactive = ! $device->acceptsLogs();

        $data = $this->prepare($request, $access, $request->validate($this->rules()));
        $device->fill($data)->save();

        // Log yang sempat tersimpan sebelum mesin aktif/dikaitkan sekolah ikut diproses.
        if ($wasInactive && $device->acceptsLogs()) {
            $device->logs()->whereNull('school_id')->update(['school_id' => $device->school_id]);
            $service->reprocess($device->logs());
        }

        AdminAuditLog::record('fingerprint.device_updated', $request->user('admin'), $device, [
            'changed_fields' => array_keys($device->getChanges()),
        ]);

        return to_route('admin.academic.fingerprint.devices.index')->with('success', 'Mesin fingerprint diperbarui.');
    }

    public function toggle(Request $request, StudentAttendanceAccess $access, FingerprintDevice $device): RedirectResponse
    {
        $this->authorizeDevice($request, $access, $device);

        if (! $device->is_active && $device->school_id === null) {
            return back()->with('error', 'Pilih sekolah untuk mesin ini lebih dulu, lalu aktifkan.');
        }

        $device->update(['is_active' => ! $device->is_active]);
        AdminAuditLog::record('fingerprint.device_toggled', $request->user('admin'), $device, ['is_active' => $device->is_active]);

        return back()->with('success', $device->is_active ? 'Mesin diaktifkan.' : 'Mesin dinonaktifkan.');
    }

    public function destroy(Request $request, StudentAttendanceAccess $access, FingerprintDevice $device): RedirectResponse
    {
        $this->authorizeDevice($request, $access, $device);

        if ($device->is_active) {
            return back()->with('error', 'Nonaktifkan mesin lebih dulu sebelum menghapusnya.');
        }

        $serial = $device->serial_number;
        $device->delete();
        AdminAuditLog::record('fingerprint.device_deleted', $request->user('admin'), null, ['serial_number' => $serial]);

        return back()->with('success', 'Mesin dihapus beserta log ketukannya. Absensi yang sudah tercatat tidak ikut terhapus.');
    }

    /** Mesin dalam cakupan sekolah pengelola, ditambah perangkat baru yang belum diklaim. */
    private function scoped(Request $request, StudentAttendanceAccess $access): Builder
    {
        $schoolIds = $access->schools($request->user('admin'))->pluck('id');

        return FingerprintDevice::query()->where(fn (Builder $q) => $q
            ->whereNull('school_id')
            ->orWhereIn('school_id', $schoolIds));
    }

    private function authorizeDevice(Request $request, StudentAttendanceAccess $access, FingerprintDevice $device): void
    {
        abort_unless($this->scoped($request, $access)->whereKey($device->id)->exists(), 404);
    }

    private function schoolOptions(Request $request, StudentAttendanceAccess $access)
    {
        return $access->schools($request->user('admin'))->with('unit:id,name')->get()->sortBy('unit.name')->values();
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'location' => ['nullable', 'string', 'max:160'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'is_active' => ['nullable', 'boolean'],
            'allowed_ips' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) {
                foreach (explode(',', (string) $value) as $entry) {
                    $entry = trim($entry);
                    if ($entry === '') {
                        continue;
                    }
                    [$ip, $mask] = array_pad(explode('/', $entry, 2), 2, null);
                    $valid = filter_var($ip, FILTER_VALIDATE_IP) !== false;
                    $max = str_contains((string) $ip, ':') ? 128 : 32;
                    if (! $valid || ($mask !== null && (! ctype_digit($mask) || (int) $mask > $max))) {
                        $fail("Alamat IP \"{$entry}\" tidak valid. Gunakan IP atau CIDR dipisah koma, mis. 203.0.113.10, 10.0.0.0/24.");

                        return;
                    }
                }
            }],
        ];
    }

    private function prepare(Request $request, StudentAttendanceAccess $access, array $data): array
    {
        $data['is_active'] = $request->boolean('is_active');
        $data['school_id'] = $data['school_id'] ?? null;
        $data['allowed_ips'] = implode(', ', array_filter(array_map('trim', explode(',', (string) ($data['allowed_ips'] ?? ''))))) ?: null;

        if ($data['school_id']) {
            // Hanya sekolah dalam cakupan pengelola yang boleh dipilih.
            $access->schools($request->user('admin'))->findOrFail((int) $data['school_id']);
        } elseif ($data['is_active']) {
            abort(422, 'Pilih sekolah sebelum mengaktifkan mesin.');
        }

        return $data;
    }
}
