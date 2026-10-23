<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Employee;
use App\Models\EmployeeStatusHistory;
use App\Models\OrganizationalAssignment;
use App\Models\OrganizationalUnitHistory;
use App\Models\OrganizationalPosition;
use App\Models\User;
use App\Rules\AdminPasswordPolicy;
use App\Services\EmployeeAccess;
use App\Services\OrganizationAccess;
use App\Services\PegawaiPortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request, EmployeeAccess $access, OrganizationAccess $org): View
    {
        $admin = $request->user('admin');
        $unitId = $request->integer('unit_id') ?: null;
        $q = trim((string) $request->query('q'));
        $status = EmployeeStatus::tryFrom((string) $request->query('status'));

        $employees = $access->scopeVisible(Employee::query(), $admin)
            ->with(['assignments' => fn ($a) => $a->with('unit:id,name')->orderByDesc('is_primary')->orderBy('start_date')])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")->orWhere('employee_number', 'like', "%{$q}%")))
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->when($unitId, fn ($query) => $query->whereHas('assignments', fn ($a) => $a
                ->whereIn('organizational_unit_id', $org->withDescendants([$unitId]))))
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('admin.employees.index', [
            'employees' => $employees,
            'units' => $access->availableUnits($admin),
            'statuses' => EmployeeStatus::cases(),
            'filters' => ['q' => $q, 'status' => $status?->value, 'unit_id' => $unitId],
        ]);
    }

    public function create(Request $request, EmployeeAccess $access): View
    {
        $admin = $request->user('admin');

        $units = $access->availableUnits($admin);

        return view('admin.employees.form', [
            'employee' => new Employee(['status' => EmployeeStatus::Active, 'employee_type' => array_key_first(Employee::typeOptions())]),
            'units' => $units,
            'unitOptions' => $access->unitOptions($units),
            'positions' => $this->positionsFor($access, $admin),
        ]);
    }

    public function store(Request $request, EmployeeAccess $access, OrganizationAccess $org, PegawaiPortalAccess $portal): RedirectResponse
    {
        $admin = $request->user('admin');
        $data = $this->validated($request) + $request->validate([
            'organizational_unit_id' => ['required', 'integer', Rule::exists('organizational_units', 'id')->where('status', 'active')],
            'organizational_position_id' => ['nullable', 'integer', Rule::exists('organizational_positions', 'id')],
            'start_date' => ['required', 'date'],
        ]);

        // Akun login portal (opsional) dibuat sekaligus, hanya bila pengelola berhak menghubungkan akun.
        $createAccount = $request->boolean('create_account') && $admin->can('employees.link_account');
        $account = $createAccount ? $request->validate([
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')],
            'password' => ['required', 'confirmed', AdminPasswordPolicy::rule()],
        ], ['email.required' => 'Email wajib diisi untuk membuat akun login.']) : null;

        $unitId = (int) $data['organizational_unit_id'];
        $org->assertCanAccess($admin, $unitId);
        $this->assertPositionInUnit($data['organizational_position_id'] ?? null, $unitId);

        // Jabatan pada penempatan awal otomatis menjadi Penugasan Organisasi (tampil di bagan),
        // hanya bila pengelola berhak mengelola penugasan.
        $positionId = $data['organizational_position_id'] ?? null;
        $autoAssign = $positionId && $admin->can('organization.assignments.manage');
        if ($autoAssign) {
            $position = OrganizationalPosition::query()->findOrFail($positionId);
            if ($position->isFull($data['start_date'], null)) {
                throw ValidationException::withMessages(['organizational_position_id' => $position->isSingleHolder()
                    ? 'Jabatan ini sudah dijabat orang lain pada tanggal tersebut. Akhiri penugasan lamanya atau pilih jabatan lain.'
                    : "Jabatan ini sudah penuh (kapasitas {$position->capacityLabel()}). Naikkan kapasitas di menu Jabatan atau pilih jabatan lain."]);
            }
        }

        $employee = DB::transaction(function () use ($data, $admin, $unitId, $positionId, $autoAssign, $account, $portal) {
            $employee = Employee::create([
                'employee_number' => $data['employee_number'], 'name' => $data['name'],
                'employee_type' => $data['employee_type'], 'status' => EmployeeStatus::Active,
                'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null, 'address' => $data['address'] ?? null,
                'hire_date' => $data['hire_date'] ?? null, 'notes' => $data['notes'] ?? null,
            ]);
            if ($account) {
                $user = User::create([
                    'name' => $employee->name, 'username' => $account['username'],
                    'email' => $account['email'], 'password' => $account['password'], 'is_active' => true,
                ]);
                $user->syncRoles([$portal->defaultRole()->id]);
                $employee->update(['user_id' => $user->id]);
                AdminAuditLog::record('employee.account_created', $admin, $employee, ['user_id' => $user->id, 'username' => $user->username]);
            }
            $employee->assignments()->create([
                'organizational_unit_id' => $unitId,
                'organizational_position_id' => $data['organizational_position_id'] ?? null,
                'is_primary' => true,
                'start_date' => $data['start_date'],
            ]);
            EmployeeStatusHistory::create([
                'employee_id' => $employee->id, 'from_status' => null, 'to_status' => EmployeeStatus::Active->value,
                'effective_date' => $data['start_date'], 'reason' => 'Pegawai didaftarkan.', 'actor_admin_id' => $admin->id,
            ]);

            if ($autoAssign) {
                $position = OrganizationalPosition::query()->findOrFail($positionId);
                $assignment = OrganizationalAssignment::create([
                    'organizational_unit_id' => $unitId, 'organizational_position_id' => $positionId,
                    'employee_id' => $employee->id, 'assignee_name' => $employee->name,
                    'start_date' => $data['start_date'], 'end_date' => null, 'status' => 'active',
                ]);
                OrganizationalUnitHistory::create([
                    'organizational_unit_id' => $unitId, 'actor_admin_id' => $admin->id, 'action' => 'assignment_created',
                    'summary' => "{$employee->name} ditugaskan sebagai {$position->name}.", 'created_at' => now(),
                ]);
                AdminAuditLog::record('organization.assignment_created', $admin, $assignment, ['unit_id' => $unitId, 'position_id' => $positionId]);
            }

            AdminAuditLog::record('employee.created', $admin, $employee, ['unit_id' => $unitId]);

            return $employee;
        });

        return redirect()->route('admin.employees.show', $employee)->with('success', $employee->user_id
            ? 'Pegawai berhasil didaftarkan beserta akun login portal.'
            : 'Pegawai berhasil didaftarkan.');
    }

    public function show(Request $request, Employee $employee, EmployeeAccess $access): View
    {
        $admin = $request->user('admin');
        $access->assertCanManage($admin, $employee);

        $employee->load([
            'user:id,name,username,email',
            'assignments' => fn ($a) => $a->with(['unit:id,name,code', 'position:id,name'])->orderByDesc('is_primary')->orderByDesc('start_date'),
            'documents' => fn ($d) => $d->with('uploader:id,name')->latest('id'),
            'statusHistories' => fn ($h) => $h->with('actor:id,name')->latest('id'),
        ]);

        return view('admin.employees.show', [
            'employee' => $employee,
            'statuses' => EmployeeStatus::cases(),
            'linkableUsers' => $admin->can('employees.link_account')
                ? \App\Models\User::query()->where('is_active', true)
                    ->where(fn ($q) => $q->whereDoesntHave('employee')->orWhere('id', $employee->user_id))
                    ->orderBy('name')->get(['id', 'name', 'username'])
                : collect(),
        ]);
    }

    public function edit(Request $request, Employee $employee, EmployeeAccess $access): View
    {
        $access->assertCanManage($request->user('admin'), $employee);

        return view('admin.employees.form', ['employee' => $employee->load('user:id,username'), 'units' => collect(), 'positions' => collect()]);
    }

    public function update(Request $request, Employee $employee, EmployeeAccess $access, PegawaiPortalAccess $portal): RedirectResponse
    {
        $admin = $request->user('admin');
        $access->assertCanManage($admin, $employee);
        $data = $this->validated($request, $employee);

        // Akun portal (opsional): buat bila belum punya, atau ganti kata sandi bila sudah punya.
        $canAccount = $admin->can('employees.link_account');
        $account = $canAccount && ! $employee->user_id && $request->boolean('create_account')
            ? $request->validate([
                'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')],
                'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')],
                'password' => ['required', 'confirmed', AdminPasswordPolicy::rule()],
            ], ['email.required' => 'Email wajib diisi untuk membuat akun login.'])
            : null;
        $reset = $canAccount && $employee->user_id && $request->boolean('reset_password')
            ? $request->validate(['password' => ['required', 'confirmed', AdminPasswordPolicy::rule()]])
            : null;

        DB::transaction(function () use ($employee, $data, $admin, $account, $reset, $portal) {
            $employee->update($data);
            AdminAuditLog::record('employee.updated', $admin, $employee, ['changed_fields' => array_keys($employee->getChanges())]);

            if ($account) {
                $user = User::create([
                    'name' => $employee->name, 'username' => $account['username'],
                    'email' => $account['email'], 'password' => $account['password'], 'is_active' => true,
                ]);
                $user->syncRoles([$portal->defaultRole()->id]);
                $employee->update(['user_id' => $user->id]);
                AdminAuditLog::record('employee.account_created', $admin, $employee, ['user_id' => $user->id, 'username' => $user->username]);
            }
            if ($reset) {
                $employee->user->update(['password' => $reset['password']]);
                AdminAuditLog::record('employee.account_password_reset', $admin, $employee, ['user_id' => $employee->user_id]);
            }
        });

        return redirect()->route('admin.employees.show', $employee)->with('success', match (true) {
            (bool) $account => 'Data pegawai diperbarui dan akun login dibuat.',
            (bool) $reset => 'Data pegawai diperbarui dan kata sandi akun diganti.',
            default => 'Data pegawai diperbarui.',
        });
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'employee_number' => ['required', 'string', 'max:40', Rule::unique('employees', 'employee_number')->ignore($employee?->id)],
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'employee_type' => ['required', Rule::in(array_keys(Employee::typeOptions(true, $employee?->employee_type)))],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'hire_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function assertPositionInUnit(mixed $positionId, int $unitId): void
    {
        if ($positionId) {
            $ok = OrganizationalPosition::query()->whereKey($positionId)
                ->where('organizational_unit_id', $unitId)->where('is_active', true)->exists();
            if (! $ok) {
                throw ValidationException::withMessages(['organizational_position_id' => 'Jabatan tidak berada pada unit yang dipilih.']);
            }
        }
    }

    private function positionsFor(EmployeeAccess $access, $admin)
    {
        return OrganizationalPosition::query()->where('is_active', true)
            ->whereIn('organizational_unit_id', $access->availableUnits($admin)->pluck('id'))
            ->orderBy('name')->get(['id', 'name', 'organizational_unit_id']);
    }
}
