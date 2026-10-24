<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zona waktu per kebijakan absensi. Kosong = ikut config('attendance.timezone'),
 * sehingga kebijakan yang sudah ada berperilaku sama seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->string('timezone', 40)->nullable()->after('late_tolerance_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
