<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Events\ActivityReportReviewed;
use App\Events\ActivityReportSubmitted;
use App\Models\ActivityBudgetItem;
use App\Models\ActivityDocument;
use App\Models\ActivityParticipant;
use App\Models\AdminAuditLog;
use App\Models\Employee;
use App\Models\WorkActivity;
use App\Models\WorkProgram;
use App\Services\EmployeeAccess;
use App\Services\OrganizationAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkActivityController extends Controller
{
    public function store(Request $request, WorkProgram $workProgram, OrganizationAccess $access): RedirectResponse
    {
        $this->assertProgramAccess($request, $workProgram, $access);
        abort_unless($workProgram->status === 'approved', 422, 'Kegiatan hanya dapat ditambahkan ke program yang sudah disetujui.');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('work_activities', 'code')->where('work_program_id', $workProgram->id)],
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:180'],
            'planned_start_at' => ['nullable', 'date'],
            'planned_end_at' => ['nullable', 'date', 'after_or_equal:planned_start_at'],
        ]);
        $admin = $request->user('admin');
        $activity = $workProgram->activities()->create([
            ...$data,
            'organizational_unit_id' => $workProgram->organizational_unit_id,
            'created_by_admin_id' => $admin->id,
            'status' => 'planned',
        ]);
        AdminAuditLog::record('work_activity.created', $admin, $activity);

        return back()->with('success', 'Kegiatan ditambahkan.');
    }

    public function updateStatus(Request $request, WorkProgram $workProgram, WorkActivity $activity, OrganizationAccess $access): RedirectResponse
    {
        $this->assertActivityAccess($request, $workProgram, $activity, $access);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(WorkActivity::STATUSES))]]);
        $transitions = [
            'planned' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];
        abort_unless(in_array($data['status'], $transitions[$activity->status] ?? [], true), 422, 'Perubahan status kegiatan tidak valid.');

        $changes = ['status' => $data['status']];
        if ($data['status'] === 'in_progress') {
            $changes['actual_start_at'] = now();
        } elseif ($data['status'] === 'completed') {
            $changes['actual_end_at'] = now();
        }
        $activity->update($changes);
        AdminAuditLog::record('work_activity.status_updated', $request->user('admin'), $activity, ['status' => $data['status']]);

        return back()->with('success', 'Status kegiatan diperbarui.');
    }

    public function storeBudgetItem(Request $request, WorkProgram $workProgram, WorkActivity $activity, OrganizationAccess $access): RedirectResponse
    {
        $this->assertActivityAccess($request, $workProgram, $activity, $access);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'planned_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'realized_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        $item = ActivityBudgetItem::updateOrCreate(
            ['work_activity_id' => $activity->id, 'name' => $data['name']],
            [
                'description' => $data['description'] ?? null,
                'planned_amount' => $data['planned_amount'],
                'realized_amount' => $data['realized_amount'] ?? 0,
            ],
        );
        AdminAuditLog::record('activity_budget.saved', $request->user('admin'), $item, [
            'activity_id' => $activity->id,
            'planned_amount' => $item->planned_amount,
            'realized_amount' => $item->realized_amount,
        ]);

        return back()->with('success', 'Rincian anggaran disimpan.');
    }

    public function storeParticipant(Request $request, WorkProgram $workProgram, WorkActivity $activity, EmployeeAccess $employees, OrganizationAccess $access): RedirectResponse
    {
        $this->assertActivityAccess($request, $workProgram, $activity, $access);
        $data = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'participant_name' => ['nullable', 'string', 'max:160', Rule::requiredIf(! $request->filled('employee_id'))],
            'role' => ['nullable', 'string', 'max:100'],
        ]);

        $employee = null;
        if (! empty($data['employee_id'])) {
            $employee = Employee::query()->findOrFail($data['employee_id']);
            $employees->assertCanManage($request->user('admin'), $employee);
            if (! $employee->assignments()->where('organizational_unit_id', $activity->organizational_unit_id)->exists()) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Pegawai harus memiliki penempatan pada unit kegiatan.',
                ]);
            }
        }

        $participantName = $employee?->name ?? trim((string) $data['participant_name']);
        $identity = ['work_activity_id' => $activity->id, 'participant_name' => $participantName];
        if ($employee) {
            $identity['employee_id'] = $employee->id;
        }
        ActivityParticipant::firstOrCreate($identity, [
            'employee_id' => $employee?->id,
            'role' => $data['role'] ?? null,
            'attendance_status' => 'planned',
        ]);

        return back()->with('success', 'Peserta ditambahkan.');
    }

    public function saveReport(Request $request, WorkProgram $workProgram, WorkActivity $activity, OrganizationAccess $access): RedirectResponse
    {
        $this->assertActivityAccess($request, $workProgram, $activity, $access);
        abort_unless(in_array($activity->status, ['in_progress', 'completed'], true), 422, 'Laporan dapat diisi saat kegiatan berjalan atau selesai.');
        $report = $activity->report;
        abort_if($report && in_array($report->status, ['submitted', 'reviewed'], true), 422, 'Laporan yang sudah diajukan tidak dapat diubah.');
        $data = $request->validate([
            'summary' => ['required', 'string', 'min:10', 'max:10000'],
            'outcomes' => ['nullable', 'string', 'max:10000'],
            'challenges' => ['nullable', 'string', 'max:10000'],
            'recommendations' => ['nullable', 'string', 'max:10000'],
        ]);

        $activity->report()->updateOrCreate([], [
            ...$data,
            'status' => 'draft',
            'submitted_by_admin_id' => $request->user('admin')->id,
        ]);

        return back()->with('success', 'Draft laporan disimpan.');
    }

    public function submitReport(Request $request, WorkProgram $workProgram, WorkActivity $activity, OrganizationAccess $access): RedirectResponse
    {
        $this->assertActivityAccess($request, $workProgram, $activity, $access);
        $report = $activity->report()->firstOrFail();
        abort_unless(in_array($report->status, ['draft', 'revision_requested'], true), 422, 'Laporan ini tidak dapat diajukan.');
        $report->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'submitted_by_admin_id' => $request->user('admin')->id,
            'evaluation_note' => null,
            'evaluated_by_admin_id' => null,
            'evaluated_at' => null,
        ]);
        ActivityReportSubmitted::dispatch($report, $request->user('admin'));

        return back()->with('success', 'Laporan kegiatan diajukan untuk evaluasi.');
    }

    public function reviewReport(Request $request, WorkProgram $workProgram, WorkActivity $activity, OrganizationAccess $access): RedirectResponse
    {
        $this->assertActivityAccess($request, $workProgram, $activity, $access);
        $report = $activity->report()->firstOrFail();
        abort_unless($report->status === 'submitted', 422, 'Laporan ini tidak sedang menunggu evaluasi.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['reviewed', 'revision_requested'])],
            'evaluation_note' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->input('decision') === 'revision_requested')],
        ]);
        $report->update([
            'status' => $data['decision'],
            'evaluation_note' => $data['evaluation_note'] ?? null,
            'evaluated_by_admin_id' => $request->user('admin')->id,
            'evaluated_at' => now(),
        ]);
        AdminAuditLog::record('activity_report.reviewed', $request->user('admin'), $report, ['decision' => $data['decision']]);
        ActivityReportReviewed::dispatch($report, $request->user('admin'), $data['decision'], $data['evaluation_note'] ?? null);

        return back()->with('success', $data['decision'] === 'reviewed' ? 'Laporan dievaluasi.' : 'Laporan dikembalikan untuk perbaikan.');
    }

    public function uploadDocument(Request $request, WorkProgram $workProgram, WorkActivity $activity, OrganizationAccess $access): RedirectResponse
    {
        $this->assertActivityAccess($request, $workProgram, $activity, $access);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
        ]);
        $file = $data['file'];
        $path = $file->store("activity-documents/{$activity->id}", 'local');
        $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'dokumen';
        ActivityDocument::create([
            'work_activity_id' => $activity->id,
            'title' => $data['title'],
            'path' => $path,
            'original_name' => mb_substr($originalName, 0, 255),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by_admin_id' => $request->user('admin')->id,
        ]);

        return back()->with('success', 'Dokumen disimpan di penyimpanan privat.');
    }

    public function downloadDocument(Request $request, WorkProgram $workProgram, WorkActivity $activity, ActivityDocument $document, OrganizationAccess $access)
    {
        $this->assertActivityAccess($request, $workProgram, $activity, $access);
        abort_unless((int) $document->work_activity_id === (int) $activity->id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
        ]);
    }

    private function assertActivityAccess(Request $request, WorkProgram $workProgram, WorkActivity $activity, OrganizationAccess $access): void
    {
        abort_unless((int) $activity->work_program_id === (int) $workProgram->id, 404);
        abort_unless((int) $activity->organizational_unit_id === (int) $workProgram->organizational_unit_id, 404);
        $access->assertCanAccess($request->user('admin'), (int) $activity->organizational_unit_id);
    }

    private function assertProgramAccess(Request $request, WorkProgram $workProgram, OrganizationAccess $access): void
    {
        $access->assertCanAccess($request->user('admin'), (int) $workProgram->organizational_unit_id);
    }
}
