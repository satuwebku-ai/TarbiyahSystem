<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\OrganizationalUnitType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Support\Dt;
use Yajra\DataTables\Facades\DataTables;

class OrganizationalUnitTypeController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if (Dt::wants($request)) {
            $query = OrganizationalUnitType::query()->withCount('units');

            return DataTables::eloquent($query->reorder())
                ->editColumn('name', fn (OrganizationalUnitType $t) => Dt::stack($t->name, $t->description))
                ->editColumn('slug', fn (OrganizationalUnitType $t) => '<code>'.e($t->slug).'</code>')
                ->editColumn('units_count', fn (OrganizationalUnitType $t) => Dt::num($t->units_count))
                ->editColumn('is_active', fn (OrganizationalUnitType $t) => Dt::badge($t->is_active))
                ->addColumn('action', fn (OrganizationalUnitType $t) => view('admin.organization.unit-types._actions', ['type' => $t])->render())
                ->orderColumn('name', 'name $1')
                ->filterColumn('name', fn ($q, $kw) => $q->where(fn ($w) => $w->where('name', 'like', "%{$kw}%")->orWhere('description', 'like', "%{$kw}%")))
                ->rawColumns(['name', 'slug', 'is_active', 'action'])
                ->make(true);
        }

        return view('admin.organization.unit-types.index');
    }

    public function create(): View
    {
        return view('admin.organization.unit-types.form', ['type' => new OrganizationalUnitType()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $type = OrganizationalUnitType::create($data);
        AdminAuditLog::record('organization.unit_type.created', auth('admin')->user(), $type);

        if (! \App\Models\OrganizationalUnit::query()->exists() && auth('admin')->user()->can('organization.units.manage')) {
            return redirect()->route('admin.organization.units.create')
                ->with('success', 'Jenis unit berhasil ditambahkan. Lanjut: buat unit pertama.');
        }

        return redirect()->route('admin.organization.unit-types.index')->with('success', 'Jenis unit berhasil ditambahkan.');
    }

    public function edit(OrganizationalUnitType $unitType): View
    {
        return view('admin.organization.unit-types.form', ['type' => $unitType]);
    }

    public function update(Request $request, OrganizationalUnitType $unitType): RedirectResponse
    {
        $unitType->update($this->validated($request, $unitType));
        AdminAuditLog::record('organization.unit_type.updated', auth('admin')->user(), $unitType);

        return redirect()->route('admin.organization.unit-types.index')->with('success', 'Jenis unit berhasil diperbarui.');
    }

    public function toggle(OrganizationalUnitType $unitType): RedirectResponse
    {
        $unitType->update(['is_active' => ! $unitType->is_active]);
        AdminAuditLog::record('organization.unit_type.status_changed', auth('admin')->user(), $unitType, [
            'is_active' => $unitType->is_active,
        ]);

        return back()->with('success', $unitType->is_active ? 'Jenis unit diaktifkan.' : 'Jenis unit dinonaktifkan.');
    }

    private function validated(Request $request, ?OrganizationalUnitType $type = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'slug' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'alpha_dash:ascii',
                Rule::unique('organizational_unit_types', 'slug')->ignore($type?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
