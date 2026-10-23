<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Events\WorkProgramReviewed;
use App\Events\WorkProgramSubmitted;
use App\Models\ActivityApproval;
use App\Models\AdminAuditLog;
use App\Models\OrganizationalUnit;
use App\Models\WorkProgram;
use App\Services\OrganizationAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkProgramController extends Controller
{
    public function index(Request $request, OrganizationAccess $access): View
    {
        $admin = $request->user('admin');
        $visibleIds = $access->visibleUnitIds($admin);
        $query = WorkProgram::query()
            ->with('unit:id,name')
            ->withCount('activities')
            ->when($visibleIds !== null, fn ($q) => $q->whereIn('organizational_unit_id', $visibleIds));

        $filters = $request->only(['q', 'unit_id', 'status']);
        if ($unitId = $request->integer('unit_id')) {
            $access->assertCanAccess($admin, $unitId);
            $query->where('organizational_unit_id', $unitId);
        }
        if ($status = $request->query('status')) {
            abort_unless(array_key_exists($status, WorkProgram::STATUSES), 422);
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }

        return view('admin.programs.index', [
            'programs' => $query->orderByDesc('start_date')->orderBy('title')->paginate(20)->withQueryString(),
            'units' => $this->availableUnits($visibleIds),
            'filters' => $filters,
            'statuses' => WorkProgram::STATUSES,
        ]);
    }

    public function create(Request $request, OrganizationAccess $access): View
    {
        $admin = $request->user('admin');
        $visibleIds = $access->visibleUnitIds($admin);

        return view('admin.programs.form', [
            'workProgram' => new WorkProgram(['start_date' => today(), 'end_date' => today()]),
            'units' => $this->availableUnits($visibleIds),
        ]);
    }

    public function store(Request $request, OrganizationAccess $access): RedirectResponse
    {
        $data = $this->validatedProgram($request);
        $admin = $request->user('admin');
        $access->assertCanAccess($admin, (int) $data['organizational_unit_id']);

        $program = DB::transaction(fn () => WorkProgram::create([
            ...$data,
            'status' => 'draft',
            'created_by_admin_id' => $admin->id,
        ]));
        AdminAuditLog::record('work_program.created', $admin, $program);

        return redirect()->route('admin.programs.show', $program)->with('success', 'Program kerja disimpan sebagai draft.');
    }

    public function show(Request $request, WorkProgram $workProgram, OrganizationAccess $access): View
    {
        $this->assertProgramAccess($request, $workProgram, $access);
        $workProgram->load([
            'unit:id,name',
            'creator:id,name',
            'approver:id,name',
            'approvals.requester:id,name',
            'approvals.reviewer:id,name',
            'activities' => fn ($query) => $query->with([
                'budgetItems', 'participants.employee:id,name,employee_number',
                'documents', 'report.evaluator:id,name', 'approvals.reviewer:id,name',
            ]),
        ]);

        return view('admin.programs.show', compact('workProgram'));
    }

    public function edit(Request $request, WorkProgram $workProgram, OrganizationAccess $access): View
    {
        $this->assertProgramAccess($request, $workProgram, $access);
        abort_unless(in_array($workProgram->status, ['draft', 'rejected'], true), 422, 'Program hanya dapat diubah saat berstatus draft atau perlu revisi.');
        $admin = $request->user('admin');
        $visibleIds = $access->visibleUnitIds($admin);

        return view('admin.programs.form', [
            'workProgram' => $workProgram,
            'units' => $this->availableUnits($visibleIds),
        ]);
    }

    public function update(Request $request, WorkProgram $workProgram, OrganizationAccess $access): RedirectResponse
    {
        $this->assertProgramAccess($request, $workProgram, $access);
        abort_unless(in_array($workProgram->status, ['draft', 'rejected'], true), 422);
        $data = $this->validatedProgram($request, $workProgram);
        $admin = $request->user('admin');
        $access->assertCanAccess($admin, (int) $data['organizational_unit_id']);

        $before = $workProgram->only(['organizational_unit_id', 'code', 'title', 'start_date', 'end_date', 'estimated_budget']);
        $workProgram->update($data);
        AdminAuditLog::record('work_program.updated', $admin, $workProgram, [
            'before' => $before,
            'changed_fields' => array_keys($workProgram->getChanges()),
        ]);

        return redirect()->route('admin.programs.show', $workProgram)->with('success', 'Program kerja diperbarui.');
    }

    public function submit(Request $request, WorkProgram $workProgram, OrganizationAccess $access): RedirectResponse
    {
        $this->assertProgramAccess($request, $workProgram, $access);
        abort_unless(in_array($workProgram->status, ['draft', 'rejected'], true), 422, 'Hanya draft atau program yang perlu revisi yang dapat diajukan.');
        $admin = $request->user('admin');

        DB::transaction(function () use ($workProgram, $admin) {
            $workProgram->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'rejected_at' => null,
            ]);
            ActivityApproval::create([
                'work_program_id' => $workProgram->id,
                'status' => 'pending',
                'requested_by_admin_id' => $admin->id,
                'requested_at' => now(),
            ]);
            AdminAuditLog::record('work_program.submitted', $admin, $workProgram);
        });

        // Setelah transaksi tersimpan: notifikasi tidak boleh terkirim untuk perubahan yang dibatalkan.
        WorkProgramSubmitted::dispatch($workProgram, $admin);

        return back()->with('success', 'Program kerja diajukan untuk persetujuan.');
    }

    public function review(Request $request, WorkProgram $workProgram, OrganizationAccess $access): RedirectResponse
    {
        $this->assertProgramAccess($request, $workProgram, $access);
        abort_unless($workProgram->status === 'submitted', 422, 'Program ini tidak sedang menunggu persetujuan.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->input('decision') === 'rejected')],
        ]);
        $admin = $request->user('admin');

        DB::transaction(function () use ($workProgram, $admin, $data) {
            $approval = $workProgram->approvals()->where('status', 'pending')->firstOrFail();
            $approval->update([
                'status' => $data['decision'],
                'note' => $data['note'] ?? null,
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => now(),
            ]);
            $workProgram->update([
                'status' => $data['decision'],
                'approved_at' => $data['decision'] === 'approved' ? now() : null,
                'rejected_at' => $data['decision'] === 'rejected' ? now() : null,
                'approved_by_admin_id' => $data['decision'] === 'approved' ? $admin->id : null,
            ]);
            AdminAuditLog::record('work_program.reviewed', $admin, $workProgram, [
                'decision' => $data['decision'],
                'note' => $data['note'] ?? null,
            ]);
        });

        WorkProgramReviewed::dispatch($workProgram, $admin, $data['decision'], $data['note'] ?? null);

        return back()->with('success', $data['decision'] === 'approved' ? 'Program kerja disetujui.' : 'Program kerja dikembalikan untuk revisi.');
    }

    private function validatedProgram(Request $request, ?WorkProgram $workProgram = null): array
    {
        $unitId = $request->input('organizational_unit_id');

        return $request->validate([
            'organizational_unit_id' => ['required', 'integer', Rule::exists('organizational_units', 'id')->where('status', 'active')],
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('work_programs', 'code')
                    ->where(fn ($query) => $query->where('organizational_unit_id', $unitId))
                    ->ignore($workProgram?->id),
            ],
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'objectives' => ['nullable', 'string', 'max:10000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'estimated_budget' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);
    }

    private function assertProgramAccess(Request $request, WorkProgram $workProgram, OrganizationAccess $access): void
    {
        $access->assertCanAccess($request->user('admin'), (int) $workProgram->organizational_unit_id);
    }

    private function availableUnits(?array $visibleIds)
    {
        return OrganizationalUnit::query()
            ->where('status', 'active')
            ->when($visibleIds !== null, fn ($query) => $query->whereIn('id', $visibleIds))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }
}
