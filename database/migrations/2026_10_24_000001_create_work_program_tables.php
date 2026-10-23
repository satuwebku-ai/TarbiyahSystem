<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->text('objectives')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('estimated_budget', 14, 2)->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organizational_unit_id', 'code']);
            $table->index(['organizational_unit_id', 'start_date', 'end_date'], 'work_program_unit_dates_idx');
        });

        Schema::create('work_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_program_id')->constrained('work_programs')->restrictOnDelete();
            // Disimpan juga pada kegiatan agar laporan per unit tetap stabil.
            $table->foreignId('organizational_unit_id')->constrained('organizational_units')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('location', 180)->nullable();
            $table->dateTime('planned_start_at')->nullable();
            $table->dateTime('planned_end_at')->nullable();
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();
            $table->string('status', 20)->default('planned')->index();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['work_program_id', 'code']);
            $table->index(['organizational_unit_id', 'status'], 'work_activity_unit_status_idx');
        });

        Schema::create('activity_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_activity_id')->constrained('work_activities')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('participant_name', 160);
            $table->string('role', 100)->nullable();
            $table->string('attendance_status', 20)->default('planned');
            $table->timestamps();
            $table->index(['work_activity_id', 'employee_id'], 'activity_participant_lookup_idx');
        });

        Schema::create('activity_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_activity_id')->constrained('work_activities')->restrictOnDelete();
            $table->string('title', 160);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('activity_budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_activity_id')->constrained('work_activities')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->decimal('planned_amount', 14, 2)->default(0);
            $table->decimal('realized_amount', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['work_activity_id', 'name']);
        });

        Schema::create('activity_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_activity_id')->unique()->constrained('work_activities')->restrictOnDelete();
            $table->text('summary')->nullable();
            $table->text('outcomes')->nullable();
            $table->text('challenges')->nullable();
            $table->text('recommendations')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('evaluated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('evaluated_at')->nullable();
            $table->string('evaluation_note', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('activity_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_program_id')->constrained('work_programs')->restrictOnDelete();
            // null berarti persetujuan program; terisi berarti persetujuan kegiatan.
            $table->foreignId('work_activity_id')->nullable()->constrained('work_activities')->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->string('note', 1000)->nullable();
            $table->foreignId('requested_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['work_program_id', 'work_activity_id', 'status'], 'activity_approval_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_approvals');
        Schema::dropIfExists('activity_reports');
        Schema::dropIfExists('activity_budget_items');
        Schema::dropIfExists('activity_documents');
        Schema::dropIfExists('activity_participants');
        Schema::dropIfExists('work_activities');
        Schema::dropIfExists('work_programs');
    }
};
