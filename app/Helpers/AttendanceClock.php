<?php

namespace App\Helpers;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Satu-satunya tempat konversi zona waktu absensi.
 * Penyimpanan = zona waktu aplikasi (UTC); logika bisnis = zona absensi (mis. Asia/Jakarta).
 * Laravel TIDAK mengonversi zona saat menyimpan Carbon, jadi selalu lewat toStorage().
 */
final class AttendanceClock
{
    public static function tz(): string
    {
        return config('attendance.timezone');
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::tz());
    }

    public static function today(): Carbon
    {
        return self::now()->startOfDay();
    }

    public static function toLocal(?CarbonInterface $time): ?Carbon
    {
        return $time ? Carbon::instance($time)->setTimezone(self::tz()) : null;
    }

    public static function toStorage(CarbonInterface $time): Carbon
    {
        return Carbon::instance($time)->setTimezone(config('app.timezone'));
    }

    /** Tanggal + jam dinding lokal -> Carbon siap simpan. */
    public static function fromLocal(string $date, string $time): Carbon
    {
        return Carbon::parse("$date $time", self::tz())->setTimezone(config('app.timezone'));
    }
}
