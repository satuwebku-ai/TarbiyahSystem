<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autentikasi dua faktor (TOTP) untuk akun staf: admins (panel pengelola) dan users (portal pegawai).
 * Pelajar tidak memakai 2FA. Kebijakan (aktif/wajib) disimpan di admin_security_settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['admins', 'users'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->text('two_factor_secret')->nullable();            // terenkripsi (cast "encrypted")
                $t->text('two_factor_recovery_codes')->nullable();    // JSON daftar hash kode pemulihan
                $t->timestamp('two_factor_confirmed_at')->nullable(); // terisi = 2FA aktif
                $t->unsignedBigInteger('two_factor_last_step')->nullable(); // langkah TOTP terakhir yang dipakai (anti pemakaian ulang)
            });
        }

        Schema::table('admin_security_settings', function (Blueprint $t) {
            $t->boolean('two_factor_enabled')->default(true);
            $t->boolean('two_factor_required_admin')->default(false);
            $t->boolean('two_factor_required_pegawai')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('admin_security_settings', function (Blueprint $t) {
            $t->dropColumn(['two_factor_enabled', 'two_factor_required_admin', 'two_factor_required_pegawai']);
        });

        foreach (['admins', 'users'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step']);
            });
        }
    }
};
