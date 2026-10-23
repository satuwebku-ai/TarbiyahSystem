<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\OrganizationalPosition;
use App\Services\EmployeeAccess;
use App\Services\OrganizationAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeAssignmentController extends Controller
{
    public function create(Request $request, Employee $employee, EmployeeAccess $access): View
    {
        $access->assertCanManage($request->user('admin'), $employee);

        return $this->form($request, $employee, new EmployeeAssignment(['start_date' => now()->toDateString()]), $access);
    }

    public function store(Request $request, Employee $employee, EmployeeAccess $access, OrganizationAccess $org): RedirectResponse
    {
        $admin = $request->user('admin');
        $access->assertCanManage($admin, $employee);
        $data = $this->validated($request, $employee, null, $org);
        $assignment = $employee->assignments()->create($data);
        AdminAuditLog::record('employee.assignment_created', $admin, $employee, $this->auditDetails($assignment));

        return redirect()->route('admin.employees.show', $employee)->with('success', 'Penempatan ditambahkan.');
    }

    public function edit(Request $request, Employee $employee, EmployeeAssignment $assignment, EmployeeAccess $access): View
    {
        $access->assertCanManage($request->user('admin'), $employee);
        abort_unless($assignment->employee_id === $employee->id, 404);

        return $this->form($request, $employee, $assignment, $access);
    }

    public function update(Request $request, Employee $employee, EmployeeAssignment $assignment, EmployeeAccess $access, OrganizationAccess $org): RedirectResponse
    {
        $admin = $request->user('admin');
        $access->assertCanManage($admin, $employee);
        abort_unless($assignment->employee_id === $employee->id, 404);
        $before = $this->auditDetails($assignment);
        $assignment->update($this->validated($request, $employee, $assignment, $org));
        AdminAuditLog::record('employee.assignment_updated', $admin, $employee, ['before' => $before, 'after' => $this->auditDetails($assignment->fresh())]);

        return redirect()->route('admin.employees.show', $employee)->with('success', 'Penempatan diperbarui.');
    }

    public function end(Request $request, Employee $employee, EmployeeAssignment $assignment, EmployeeAccess $access): RedirectResponse
    {
        $admin = $request->user('admin');
        $access->assertCanManage($admin, $employee);
        abort_unless($assignment->employee_id === $employee->id, 404);

        $data = $request->validate(['end_date' => ['required', 'date', 'after_or_equal:'.$assignment->start_date->toDateString()]]);
        $assignment->update(['end_date' => $data['end_date']]);
        AdminAuditLog::record('employee.assignment_ended', $admin, $employee, ['assignment_id' => $assignment->id, 'end_date' => $data['end_date']]);

        return back()->with('success', 'Penempatan diakhiri.');
    }

    private function auditDetails(EmployeeAssignment $a): array
    {
        return [
            'assignment_id' => $a->id,
            'unit_id' => $a->organizational_unit_id,
            'position_id' => $a->organizational_position_id,
            'is_primary' => (bool) $a->is_primary,
            'start_date' => $a->start_date?->toDateString(),
            'end_date' => $a->end_date?->toDateString(),
        ];
    }

    private function form(Request $request, Employee $employee, EmployeeAssignment $assignment, EmployeeAccess $access): View
    {
        $units = $access->availableUnits($request->user('admin'));

        return view('admin.employees.assignment-form', [
            'employee' => $employee,
            'assignment' => $assignment,
            'units' => $units,
            'unitOptions' => $access->unitOptions($units),
            'positions' => OrganizationalPosition::query()->where('is_active', true)
                ->whereIn('organizational_unit_id', $units->pluck('id'))->orderBy('name')->get(['id', 'name', 'organizational_unit_id']),
        ]);
    }

    private function validated(Request $request, Employee $employee, ?EmployeeAssignment $current, OrganizationAccess $org): array
    {
        $data = $request->validate([
            'organizational_unit_id' => ['required', 'integer', Rule::exists('organizational_units', 'id')->where('status', 'active')],
            'organizational_position_id' => ['nullable', 'integer', Rule::exists('organizational_positions', 'id')],
            'is_primary' => ['nullable', 'boolean'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $data['is_primary'] = (bool) ($data['is_primary'] ?? false);

        $org->assertCanAccess($request->user('admin'), (int) $data['organizational_unit_id']);

        if (! empty($data['organizational_position_id'])) {
            $ok = OrganizationalPosition::query()->whereKey($data['organizational_position_id'])
                ->where('organizational_unit_id', $data['organizational_unit_id'])
                ->where('is_active', true)->exists();
            if (! $ok) {
                throw ValidationException::withMessages(['organizational_position_id' => 'Jabatan tidak berada pada unit yang dipilih.']);
            }
        }

        if ($data['is_primary']) {
            $end = $data['end_date'] ?? '9999-12-31';
            $clash = EmployeeAssignment::query()
                ->where('employee_id', $employee->id)->where('is_primary', true)
                ->when($current, fn ($q) => $q->whereKeyNot($current->id))
                ->where('start_date', '<=', $end)
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $data['start_date']))
                ->exists();
            if ($clash) {
                throw ValidationException::withMessages(['is_primary' => 'Sudah ada penempatan utama pada rentang tanggal ini.']);
            }
        }

        return $data;
    }
}
