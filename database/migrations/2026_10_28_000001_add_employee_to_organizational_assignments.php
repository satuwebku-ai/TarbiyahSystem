<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan organisasi kini merujuk ke data pegawai (employees). Kolom assignee_name tetap
 * disimpan sebagai salinan nama agar bagan dan riwayat tetap terbaca walau data pegawai berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizational_assignments', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('admin_id')
                ->constrained('employees')->nullOnDelete();
        });

        // Hubungkan penugasan lama otomatis bila namanya cocok dengan tepat satu pegawai
        // yang ditempatkan di unit yang sama. Yang ambigu atau tidak cocok dibiarkan kosong.
        DB::table('organizational_assignments')->whereNull('employee_id')->orderBy('id')->each(function ($row) {
            $matches = DB::table('employees')
                ->join('employee_assignments', 'employee_assignments.employee_id', '=', 'employees.id')
                ->where('employee_assignments.organizational_unit_id', $row->organizational_unit_id)
                ->where('employees.name', $row->assignee_name)
                ->distinct()
                ->pluck('employees.id');

            if ($matches->count() === 1) {
                DB::table('organizational_assignments')->where('id', $row->id)->update(['employee_id' => $matches->first()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizational_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
