<?php

namespace App\Helpers;

use App\Services\AttendancePolicyResolver;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Satu-satunya tempat konversi zona waktu absensi.
 * Penyimpanan = zona waktu aplikasi (UTC); logika bisnis = zona absensi.
 *
 * Zona absensi ditentukan per kebijakan (attendance_policies.timezone). Semua method menerima
 * $tz opsional; tanpa $tz dipakai zona bawaan config('attendance.timezone').
 * Laravel TIDAK mengonversi zona saat menyimpan Carbon, jadi selalu lewat toStorage().
 */
final class AttendanceClock
{
    /** Zona bawaan bila $tz kosong atau tidak dikenal. */
    public static function tz(?string $tz = null): string
    {
        return $tz && in_array($tz, timezone_identifiers_list(), true) ? $tz : config('attendance.timezone');
    }

    /** Zona yang berlaku bagi unit (lewat kebijakan unit itu, induk, atau umum). */
    public static function forUnit(?int $unitId): string
    {
        return app(AttendancePolicyResolver::class)->timezoneForUnit($unitId);
    }

    /** Singkatan WIB/WITA/WIT untuk ditampilkan di samping jam. */
    public static function abbr(?string $tz = null): string
    {
        $tz = self::tz($tz);

        return config("attendance.timezones.{$tz}.0") ?? $tz;
    }

    public static function now(?string $tz = null): Carbon
    {
        return Carbon::now(self::tz($tz));
    }

    public static function today(?string $tz = null): Carbon
    {
        return self::now($tz)->startOfDay();
    }

    public static function toLocal(?CarbonInterface $time, ?string $tz = null): ?Carbon
    {
        return $time ? Carbon::instance($time)->setTimezone(self::tz($tz)) : null;
    }

    public static function toStorage(CarbonInterface $time): Carbon
    {
        return Carbon::instance($time)->setTimezone(config('app.timezone'));
    }

    /** Tanggal + jam dinding lokal -> Carbon siap simpan. */
    public static function fromLocal(string $date, string $time, ?string $tz = null): Carbon
    {
        return Carbon::parse("$date $time", self::tz($tz))->setTimezone(config('app.timezone'));
    }
}
