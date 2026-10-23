<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->date('attendance_date');
            $table->string('name', 60)->default('Harian');
            $table->foreignId('recorded_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(
                ['classroom_id', 'attendance_date', 'name'],
                'student_attendance_session_unique'
            );
            $table->index(['attendance_date', 'classroom_id']);
        });

        Schema::create('student_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_attendance_session_id')
                ->constrained('student_attendance_sessions', 'id', 'sa_records_session_fk')
                ->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('present')->index();
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(
                ['student_attendance_session_id', 'student_id'],
                'student_attendance_record_unique'
            );
            $table->index(['student_id', 'created_at']);
        });

        Schema::create('student_attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_attendance_record_id')
                ->constrained('student_attendance_records', 'id', 'sa_corrections_record_fk')
                ->cascadeOnDelete();
            $table->string('previous_status', 20);
            $table->string('new_status', 20);
            $table->string('previous_notes', 500)->nullable();
            $table->string('new_notes', 500)->nullable();
            $table->string('reason', 500);
            $table->foreignId('changed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_attendance_record_id', 'created_at'], 'student_attendance_corrections_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendance_corrections');
        Schema::dropIfExists('student_attendance_records');
        Schema::dropIfExists('student_attendance_sessions');
    }
};