<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\EmployeeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Dokumen pegawai disimpan di disk privat (bukan public) dan hanya bisa diunduh lewat route berizin. */
class EmployeeDocumentController extends Controller
{
    public function store(Request $request, Employee $employee, EmployeeAccess $access): RedirectResponse
    {
        $admin = $request->user('admin');
        $access->assertCanManage($admin, $employee);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'file' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $file = $data['file'];
        $document = $employee->documents()->create([
            'title' => $data['title'],
            'path' => $file->store("employee-documents/{$employee->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by_admin_id' => $admin->id,
        ]);
        AdminAuditLog::record('employee.document_uploaded', $admin, $employee, ['document_id' => $document->id, 'title' => $document->title]);

        return back()->with('success', 'Dokumen diunggah.');
    }

    public function download(Request $request, Employee $employee, EmployeeDocument $document, EmployeeAccess $access): StreamedResponse
    {
        $access->assertCanManage($request->user('admin'), $employee);
        abort_unless($document->employee_id === $employee->id && Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(Request $request, Employee $employee, EmployeeDocument $document, EmployeeAccess $access): RedirectResponse
    {
        $admin = $request->user('admin');
        $access->assertCanManage($admin, $employee);
        abort_unless($document->employee_id === $employee->id, 404);

        AdminAuditLog::record('employee.document_deleted', $admin, $employee, ['document_id' => $document->id, 'title' => $document->title]);
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('success', 'Dokumen dihapus.');
    }
}
