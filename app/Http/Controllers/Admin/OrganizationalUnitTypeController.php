<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\OrganizationalUnitType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationalUnitTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.organization.unit-types.index', [
            'types' => OrganizationalUnitType::query()
                ->withCount('units')
                ->orderBy('name')
                ->paginate(20),
        ]);
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
