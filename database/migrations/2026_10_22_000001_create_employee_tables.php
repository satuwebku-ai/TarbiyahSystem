<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kepegawaian: data pegawai/guru, penempatan, dokumen, dan riwayat status. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            // Akun login portal pegawai (tabel users). Satu akun hanya boleh dihubungkan ke satu pegawai.
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('employee_number', 40)->unique();
            $table->string('name', 160);
            $table->string('employee_type', 20)->index();   // teacher | staff
            $table->string('status', 20)->default('active')->index();
            $table->string('email', 160)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->date('hire_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->foreignId('organizational_position_id')->nullable()->constrained('organizational_positions')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'start_date', 'end_date']);
            $table->index(['organizational_unit_id', 'start_date', 'end_date'], 'emp_assign_unit_dates_idx');
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->date('effective_date');
            $table->string('reason', 500)->nullable();
            $table->foreignId('actor_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_status_histories');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_assignments');
        Schema::dropIfExists('employees');
    }
};
