<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Token akses aplikasi mobile (pegawai = users, pelajar = student_accounts). Yang disimpan hanya hash SHA-256. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name', 100)->default('mobile');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_tokens');
    }
};
