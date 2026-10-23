<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Absensi dengan bukti lokasi (GPS) dan foto selfie; dinyalakan per kebijakan absensi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->boolean('require_location')->default(false)->after('late_tolerance_minutes');
            $table->decimal('geo_latitude', 10, 7)->nullable()->after('require_location');
            $table->decimal('geo_longitude', 10, 7)->nullable()->after('geo_latitude');
            $table->unsignedSmallInteger('geo_radius_m')->default(100)->after('geo_longitude');
            $table->boolean('require_photo')->default(false)->after('geo_radius_m');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            foreach (['check_in', 'check_out'] as $p) {
                $table->decimal("{$p}_lat", 10, 7)->nullable();
                $table->decimal("{$p}_lng", 10, 7)->nullable();
                $table->unsignedSmallInteger("{$p}_accuracy_m")->nullable();
                $table->unsignedInteger("{$p}_distance_m")->nullable();
                $table->string("{$p}_photo", 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $cols = [];
            foreach (['check_in', 'check_out'] as $p) {
                foreach (['lat', 'lng', 'accuracy_m', 'distance_m', 'photo'] as $c) {
                    $cols[] = "{$p}_{$c}";
                }
            }
            $table->dropColumn($cols);
        });
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->dropColumn(['require_location', 'geo_latitude', 'geo_longitude', 'geo_radius_m', 'require_photo']);
        });
    }
};
