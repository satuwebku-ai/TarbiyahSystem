<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sidik foto selfie (SHA-256 berkas asli) untuk menolak foto yang dipakai ulang, dan penanda
 * kecurigaan (lihat AttendancePhotoGuard) agar admin tahu foto mana yang perlu diperiksa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            foreach (['check_in', 'check_out'] as $p) {
                $table->char("{$p}_photo_hash", 64)->nullable()->index();
                $table->string("{$p}_photo_flag", 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            foreach (['check_in', 'check_out'] as $p) {
                $table->dropIndex(["{$p}_photo_hash"]);
            }
            $table->dropColumn(['check_in_photo_hash', 'check_in_photo_flag', 'check_out_photo_hash', 'check_out_photo_flag']);
        });
    }
};
