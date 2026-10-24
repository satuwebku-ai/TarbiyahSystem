<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Employee;
use App\Models\EmployeeType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use App\Support\Dt;
use Yajra\DataTables\Facades\DataTables;

class EmployeeTypeController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if (Dt::wants($request)) {
            $query = EmployeeType::query()->addSelect([
                'employees_total' => Employee::query()->selectRaw('COUNT(*)')->whereColumn('employees.employee_type', 'employee_types.code'),
            ])->addSelect('employee_types.*');

            return DataTables::eloquent($query)
                ->editColumn('name', fn (EmployeeType $t) => Dt::stack($t->name, $t->description))
                ->editColumn('code', fn (EmployeeType $t) => '<code>'.e($t->code).'</code>')
                ->editColumn('employees_total', fn (EmployeeType $t) => Dt::num($t->employees_total))
                ->editColumn('is_active', fn (EmployeeType $t) => Dt::badge($t->is_active))
                ->addColumn('action', fn (EmployeeType $t) => view('admin.employees.types._actions', ['type' => $t])->render())
                ->orderColumn('employees_total', 'employees_total $1')
                ->filterColumn('name', fn ($q, $kw) => $q->where(fn ($w) => $w->where('name', 'like', "%{$kw}%")->orWhere('description', 'like', "%{$kw}%")))
                ->rawColumns(['name', 'code', 'is_active', 'action'])
                ->make(true);
        }

        return view('admin.employees.types.index');
    }

    public function create(): View
    {
        return view('admin.employees.types.form', ['type' => new EmployeeType()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = EmployeeType::create($this->validated($request));
        AdminAuditLog::record('employee.type_created', auth('admin')->user(), $type);

        return redirect()->route('admin.employees.types.index')->with('success', 'Jenis pegawai ditambahkan.');
    }

    public function edit(EmployeeType $type): View
    {
        return view('admin.employees.types.form', ['type' => $type]);
    }

    public function update(Request $request, EmployeeType $type): RedirectResponse
    {
        // Kode dipakai pegawai; tidak boleh diubah setelah dibuat.
        $type->update($this->validated($request, $type));
        AdminAuditLog::record('employee.type_updated', auth('admin')->user(), $type);

        return redirect()->route('admin.employees.types.index')->with('success', 'Jenis pegawai diperbarui.');
    }

    public function toggle(EmployeeType $type): RedirectResponse
    {
        $type->update(['is_active' => ! $type->is_active]);
        AdminAuditLog::record('employee.type_status_changed', auth('admin')->user(), $type, ['is_active' => $type->is_active]);

        return back()->with('success', $type->is_active ? 'Jenis pegawai diaktifkan.' : 'Jenis pegawai dinonaktifkan.');
    }

    private function validated(Request $request, ?EmployeeType $type = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'code' => [$type ? 'prohibited' : 'required', 'string', 'min:2', 'max:20', 'alpha_dash:ascii', Rule::unique('employee_types', 'code')],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ]);
        if (isset($data['code'])) {
            $data['code'] = strtolower($data['code']);
        }

        return $data;
    }
}
