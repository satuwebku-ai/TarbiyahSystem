<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kredensial aplikasi Node fingerprint di sekolah: username + password (di-hash) + token (hanya hash-nya disimpan).
        Schema::create('fingerprint_node_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('username', 40)->unique();
            $table->string('password');
            $table->string('token_hash', 64)->unique();
            $table->string('token_prefix', 12);
            $table->timestamp('token_rotated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('allowed_ips', 255)->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_log_at')->nullable();
            $table->unsignedBigInteger('total_logs')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_node_connections');
    }
};
