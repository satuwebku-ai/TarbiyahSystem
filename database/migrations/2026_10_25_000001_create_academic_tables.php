<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Data akademik: sekolah, tahun ajaran, kelas, pelajar, wali, pendaftaran, dan penempatan kelas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizational_unit_id')->unique()->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('school_level_id')->constrained('school_levels')->restrictOnDelete();
            $table->string('npsn', 20)->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('name', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'is_active']);
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('grade_label', 40)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->unique(['academic_year_id', 'name']);
            $table->index(['school_id', 'academic_year_id']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            // Akun login pelajar (tabel student_accounts). Satu akun hanya untuk satu pelajar.
            $table->foreignId('student_account_id')->nullable()->unique()->constrained('student_accounts')->nullOnDelete();
            $table->string('nis', 30);
            $table->string('nisn', 20)->nullable()->unique();
            $table->string('name', 160);
            $table->string('gender', 10)->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->unique(['school_id', 'nis']);
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'name']);
        });

        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('relationship', 60);
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 500)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['student_id', 'is_primary']);
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->date('enrollment_date');
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year_id']);
        });

        Schema::create('student_class_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->index(['student_enrollment_id', 'start_date'], 'student_placement_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_class_placements');
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('students');
        Schema::dropIfExists('classrooms');
        Schema::dropIfExists('academic_years');
        Schema::dropIfExists('schools');
        Schema::dropIfExists('school_levels');
    }
};
