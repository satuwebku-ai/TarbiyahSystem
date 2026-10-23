<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daftar IP/CIDR (dipisah koma) yang boleh mengirim data untuk mesin ini. Kosong = tanpa batas.
        Schema::table('fingerprint_devices', function (Blueprint $table) {
            $table->string('allowed_ips', 255)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('fingerprint_devices', function (Blueprint $table) {
            $table->dropColumn('allowed_ips');
        });
    }
};
