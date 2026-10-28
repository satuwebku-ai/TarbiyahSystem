<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batas akhir check-in pagi per kebijakan absensi (waktu lokal zona kebijakan). Kosong = tanpa batas,
 * sehingga kebijakan yang sudah ada berperilaku sama seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->time('checkin_deadline')->nullable()->after('late_tolerance_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->dropColumn('checkin_deadline');
        });
    }
};
