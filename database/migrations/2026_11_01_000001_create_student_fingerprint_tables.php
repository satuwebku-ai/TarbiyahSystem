<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PIN siswa di mesin fingerprint (unik per sekolah).
        Schema::table('students', function (Blueprint $table) {
            $table->string('fingerprint_pin', 30)->nullable()->after('nisn');
            $table->unique(['school_id', 'fingerprint_pin'], 'students_school_pin_unique');
        });

        // Asal catatan absensi: manual (admin), fingerprint (mesin), auto (alpa otomatis).
        Schema::table('student_attendance_records', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('notes');
            $table->timestamp('checked_in_at')->nullable()->after('source');
        });

        Schema::create('fingerprint_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number', 60)->unique();
            $table->string('name', 120);
            $table->string('location', 160)->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_log_at')->nullable();
            $table->string('last_stamp', 40)->nullable();
            $table->unsignedBigInteger('total_logs')->default(0);
            $table->timestamps();
            $table->index(['school_id', 'is_active']);
        });

        Schema::create('fingerprint_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fingerprint_device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pin', 30);
            $table->timestamp('punched_at');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedSmallInteger('verify_mode')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result', 30)->default('pending')->index();
            $table->string('note', 255)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['fingerprint_device_id', 'pin', 'punched_at'], 'fingerprint_logs_unique_punch');
            $table->index(['school_id', 'punched_at']);
            $table->index(['pin', 'school_id']);
        });

        // school_id kosong = pengaturan umum (default semua sekolah).
        Schema::create('student_attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->boolean('fingerprint_enabled')->default(true);
            $table->string('session_name', 60)->default('Harian');
            $table->json('school_days');
            $table->string('start_time', 5)->default('07:00');
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(10);
            $table->string('checkin_open_time', 5)->default('05:00');
            $table->string('checkin_close_time', 5)->default('12:00');
            $table->boolean('skip_holidays')->default(true);
            $table->boolean('auto_absent_enabled')->default(false);
            $table->string('auto_absent_time', 5)->default('10:00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendance_settings');
        Schema::dropIfExists('fingerprint_logs');
        Schema::dropIfExists('fingerprint_devices');
        Schema::table('student_attendance_records', function (Blueprint $table) {
            $table->dropColumn(['source', 'checked_in_at']);
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique('students_school_pin_unique');
            $table->dropColumn('fingerprint_pin');
        });
    }
};
