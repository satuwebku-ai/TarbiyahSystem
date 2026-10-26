<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Foto acuan wajah pegawai dan hasil pencocokan (skor + status) pada tiap absen. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_face_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('path', 500);
            $table->foreignId('uploaded_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('consent_confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            foreach (['check_in', 'check_out'] as $p) {
                $table->decimal("{$p}_face_score", 5, 2)->nullable();
                $table->string("{$p}_face_status", 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['check_in_face_score', 'check_in_face_status', 'check_out_face_score', 'check_out_face_status']);
        });
        Schema::dropIfExists('employee_face_references');
    }
};
