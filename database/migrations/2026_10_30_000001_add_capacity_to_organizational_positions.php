<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Kapasitas pemegang jabatan: 1 = satu orang (bawaan), angka lain = beberapa orang, NULL = tanpa batas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizational_positions', function (Blueprint $table) {
            $table->unsignedSmallInteger('capacity')->nullable()->default(1)->after('description');
        });

        // Jabatan bersifat umum (staf/pegawai/anggota) otomatis tanpa batas; sisanya tetap satu orang.
        DB::table('organizational_positions')->where(function ($q) {
            foreach (['Pegawai%', 'Staf%', 'Staff%', 'Anggota%'] as $pattern) {
                $q->orWhere('name', 'like', $pattern);
            }
        })->update(['capacity' => null]);
    }

    public function down(): void
    {
        Schema::table('organizational_positions', function (Blueprint $table) {
            $table->dropColumn('capacity');
        });
    }
};
