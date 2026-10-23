<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Absensi pegawai/guru: kebijakan, hari libur, catatan harian, dan pengajuan koreksi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_policies', function (Blueprint $table) {
            $table->id();
            // null = kebijakan umum yayasan; unit dengan kebijakan sendiri menimpa induknya.
            $table->foreignId('organizational_unit_id')->nullable()->constrained('organizational_units')->restrictOnDelete();
            $table->string('name', 120);
            $table->json('work_days');               // hari kerja ISO: 1=Senin ... 7=Minggu
            $table->time('work_start');
            $table->time('work_end');
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organizational_unit_id', 'is_active']);
        });

        Schema::create('attendance_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date');
            $table->string('name', 150);
            $table->foreignId('organizational_unit_id')->nullable()->constrained('organizational_units')->restrictOnDelete();
            $table->timestamps();
            $table->index(['holiday_date', 'organizational_unit_id']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            // Unit saat absensi terjadi, agar rekap per unit tetap benar setelah pegawai pindah.
            $table->foreignId('organizational_unit_id')->nullable()->constrained('organizational_units')->restrictOnDelete();
            $table->date('attendance_date');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->string('status', 20);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('work_minutes')->nullable();
            $table->time('scheduled_start')->nullable();
            $table->time('scheduled_end')->nullable();
            $table->string('source', 20)->default('web');   // web | correction | manual
            $table->string('check_in_ip', 45)->nullable();
            $table->string('check_out_ip', 45)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'attendance_date']);
            $table->index(['organizational_unit_id', 'attendance_date']);
            $table->index(['attendance_date', 'status']);
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->nullOnDelete();
            $table->date('attendance_date');
            $table->time('requested_check_in')->nullable();
            $table->time('requested_check_out')->nullable();
            $table->string('reason', 500);
            $table->string('status', 20)->default('pending')->index();
            // Akun pegawai (tabel users) yang mengajukan koreksi.
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_holidays');
        Schema::dropIfExists('attendance_policies');
    }
};
