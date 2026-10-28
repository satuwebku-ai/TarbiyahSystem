<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token push (Expo) per sesi perangkat. Disimpan di baris mobile_tokens yang sama dengan sesi login,
 * sehingga keluar dari akun atau token kedaluwarsa otomatis berhenti menerima notifikasi di perangkat itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_tokens', function (Blueprint $table) {
            $table->string('push_token', 255)->nullable()->after('token_hash')->index();
            $table->string('push_platform', 16)->nullable()->after('push_token');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_tokens', function (Blueprint $table) {
            $table->dropIndex(['push_token']);
            $table->dropColumn(['push_token', 'push_platform']);
        });
    }
};
