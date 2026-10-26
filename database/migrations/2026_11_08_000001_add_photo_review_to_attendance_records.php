<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Keputusan admin atas foto absen (sesuai / mencurigakan) beserta catatan, pemutus, dan waktunya. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('photo_review_status', 12)->nullable()->index();
            $table->string('photo_review_note', 500)->nullable();
            $table->foreignId('photo_reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('photo_reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('photo_reviewed_by_admin_id');
            $table->dropIndex(['photo_review_status']);
            $table->dropColumn(['photo_review_status', 'photo_review_note', 'photo_reviewed_at']);
        });
    }
};
