<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Employee;
use App\Models\EmployeeStatusHistory;
use App\Services\EmployeeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeStatusController extends Controller
{
    public function store(Request $request, Employee $employee, EmployeeAccess $access): RedirectResponse
    {
        $admin = $request->user('admin');
        $access->assertCanManage($admin, $employee);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_map(fn ($s) => $s->value, EmployeeStatus::cases()))],
            'effective_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $new = EmployeeStatus::from($data['status']);

        if ($new === $employee->status) {
            return back()->withErrors(['status' => 'Status tidak berubah.']);
        }

        $from = $employee->status;

        DB::transaction(function () use ($employee, $new, $from, $data, $admin) {
            EmployeeStatusHistory::create([
                'employee_id' => $employee->id, 'from_status' => $employee->status->value, 'to_status' => $new->value,
                'effective_date' => $data['effective_date'], 'reason' => $data['reason'] ?? null, 'actor_admin_id' => $admin->id,
            ]);
            $employee->update(['status' => $new]);
            AdminAuditLog::record('employee.status_changed', $admin, $employee, [
                'from' => $from->value, 'to' => $new->value, 'effective_date' => $data['effective_date'],
            ]);

            // Hubungan kerja berakhir: tutup penempatan yang masih berjalan pada tanggal berlaku.
            if ($new->isEnded()) {
                $employee->assignments()->whereNull('end_date')
                    ->where('start_date', '<=', $data['effective_date'])
                    ->update(['end_date' => $data['effective_date']]);
            }
        });

        return back()->with('success', 'Status kepegawaian diperbarui menjadi '.$new->label().'.');
    }
}
