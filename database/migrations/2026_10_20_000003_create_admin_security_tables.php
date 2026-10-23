<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_security_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('max_login_attempts')->default(5);
            $table->unsignedTinyInteger('lockout_minutes')->default(15);
            $table->unsignedSmallInteger('session_idle_minutes')->default(30);
            $table->unsignedTinyInteger('password_min_length')->default(12);
            $table->boolean('password_require_mixed_case')->default(true);
            $table->boolean('password_require_numbers')->default(true);
            $table->boolean('password_require_symbols')->default(false);
            $table->timestamps();
        });

        DB::table('admin_security_settings')->insert([
            'id' => 1,
            'max_login_attempts' => 5,
            'lockout_minutes' => 15,
            'session_idle_minutes' => 30,
            'password_min_length' => 12,
            'password_require_mixed_case' => true,
            'password_require_numbers' => true,
            'password_require_symbols' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('event', 100)->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('admin_security_settings');
    }
};
