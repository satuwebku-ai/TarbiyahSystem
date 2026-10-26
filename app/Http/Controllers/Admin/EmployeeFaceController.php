<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Employee;
use App\Models\EmployeeFaceReference;
use App\Services\EmployeeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Foto acuan wajah pegawai: disimpan di disk privat, hanya bisa dilihat lewat route berizin. Hanya admin yang mengunggah. */
class EmployeeFaceController extends Controller
{
    public function store(Request $request, Employee $employee, EmployeeAccess $access): RedirectResponse
    {
        $admin = $request->user('admin');
        $access->assertCanManage($admin, $employee);

        $data = $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:3072', 'dimensions:min_width=200,min_height=200'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'Centang pernyataan bahwa pegawai telah memberi persetujuan tertulis.',
            'photo.dimensions' => 'Foto terlalu kecil (minimal 200x200 piksel).',
        ]);

        if ($employee->faceReferences()->count() >= (int) config('face.max_references', 3)) {
            return back()->withErrors(['photo' => 'Jumlah foto acuan sudah mencapai batas. Hapus salah satu terlebih dahulu.']);
        }

        $ref = $employee->faceReferences()->create([
            'path' => $data['photo']->store("face-references/{$employee->id}", 'local'),
            'uploaded_by_admin_id' => $admin->id,
            'consent_confirmed_at' => now(),
        ]);
        AdminAuditLog::record('employee.face_reference_added', $admin, $employee, ['reference_id' => $ref->id]);

        return back()->with('success', 'Foto acuan wajah ditambahkan.');
    }

    public function image(Request $request, Employee $employee, EmployeeFaceReference $face, EmployeeAccess $access): Response
    {
        $access->assertCanManage($request->user('admin'), $employee);
        abort_unless($face->employee_id === $employee->id && Storage::disk('local')->exists($face->path), 404);

        return Storage::disk('local')->response($face->path, null, ['Cache-Control' => 'no-store, private']);
    }

    public function destroy(Request $request, Employee $employee, EmployeeFaceReference $face, EmployeeAccess $access): RedirectResponse
    {
        $admin = $request->user('admin');
        $access->assertCanManage($admin, $employee);
        abort_unless($face->employee_id === $employee->id, 404);

        AdminAuditLog::record('employee.face_reference_deleted', $admin, $employee, ['reference_id' => $face->id]);
        Storage::disk('local')->delete($face->path);
        $face->delete();

        return back()->with('success', 'Foto acuan wajah dihapus.');
    }
}
