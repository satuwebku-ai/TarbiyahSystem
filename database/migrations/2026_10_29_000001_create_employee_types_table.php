<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Jenis pegawai dinamis (Guru, Tenaga kependidikan, dan jenis lain yang ditambah pengelola). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $rows = ['teacher' => 'Guru', 'staff' => 'Tenaga kependidikan'];
        // Kode yang sudah dipakai pegawai lama tetap tersedia.
        foreach (DB::table('employees')->distinct()->pluck('employee_type') as $code) {
            $rows[$code] ??= ucfirst((string) $code);
        }
        foreach ($rows as $code => $name) {
            DB::table('employee_types')->insert(['code' => $code, 'name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_types');
    }
};
