<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiga jenis akun login, masing-masing satu tabel dan satu guard:
 *
 *   admins           Pengurus Besar, KOMWIL (provinsi), KOMDA (kab/kota)   guard "admin"   pengelola.<domain>
 *   users            Pegawai dan guru                                       guard "pegawai" pegawai.<domain>
 *   student_accounts Pelajar (login memakai NIS)                            guard "pelajar" pelajar.<domain>
 *
 * Tabel ini hanya untuk login. Data orangnya ada di employees (pegawai/guru) dan students (pelajar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username', 50)->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username', 50)->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('student_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('nis', 30)->unique();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Satu tabel token reset per jenis akun (broker di config/auth.php).
        foreach (['admin_password_reset_tokens', 'password_reset_tokens', 'student_account_password_reset_tokens'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_account_password_reset_tokens');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('admin_password_reset_tokens');
        Schema::dropIfExists('student_accounts');
        Schema::dropIfExists('users');
        Schema::dropIfExists('admins');
    }
};
