<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Services\OrganizationAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Foto selfie absen: disimpan di disk privat dan hanya dilayani ke pengelola yang berhak pada unit tersebut. */
class AttendanceProofController extends Controller
{
    public function photo(Request $request, AttendanceRecord $record, string $type, OrganizationAccess $org): StreamedResponse
    {
        abort_unless(in_array($type, ['in', 'out'], true), 404);

        $admin = $request->user('admin');
        $record->organizational_unit_id
            ? $org->assertCanAccess($admin, (int) $record->organizational_unit_id)
            : abort_unless($org->visibleUnitIds($admin) === null, 403);

        $path = $type === 'in' ? $record->check_in_photo : $record->check_out_photo;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store']);
    }
}
