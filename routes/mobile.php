<?php

use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\EmployeeAttendanceController;
use App\Http\Controllers\Api\Mobile\NotificationController;
use App\Http\Controllers\Api\Mobile\StudentAttendanceController;
use App\Http\Controllers\Api\Mobile\TwoFactorEnrollmentController;
use Illuminate\Support\Facades\Route;

// API aplikasi mobile (tarbiyah-mobile) — dimuat di bootstrap/app.php dengan prefix "api/mobile".
// Autentikasi token Bearer (tabel mobile_tokens); tanpa sesi dan CSRF. Kontrak lengkap: docs/MOBILE-API.md.

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login');

// Pendaftaran 2FA pegawai: dibuka dengan username + kata sandi (akun wajib 2FA belum bisa mendapat token).
Route::post('two-factor/setup', [TwoFactorEnrollmentController::class, 'setup'])->middleware('throttle:10,1')->name('two-factor.setup');
Route::post('two-factor/confirm', [TwoFactorEnrollmentController::class, 'confirm'])->middleware('throttle:10,1')->name('two-factor.confirm');

Route::middleware(['mobile.auth', 'throttle:120,1'])->group(function () {
    Route::get('me', [AuthController::class, 'me'])->name('me');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::middleware('mobile.auth:pegawai')->prefix('employee/attendance')->name('employee.attendance.')->group(function () {
        Route::get('dashboard', [EmployeeAttendanceController::class, 'dashboard'])->name('dashboard');
        Route::get('history', [EmployeeAttendanceController::class, 'history'])->name('history');
        Route::get('corrections', [EmployeeAttendanceController::class, 'corrections'])->name('corrections.index');
        Route::post('corrections', [EmployeeAttendanceController::class, 'storeCorrection'])->middleware('throttle:10,1')->name('corrections.store');
        Route::post('check-in', [EmployeeAttendanceController::class, 'checkIn'])->middleware('throttle:20,1')->name('check-in');
        Route::post('check-out', [EmployeeAttendanceController::class, 'checkOut'])->middleware('throttle:20,1')->name('check-out');
    });

    Route::get('student/attendance', StudentAttendanceController::class)
        ->middleware('mobile.auth:pelajar')->name('student.attendance');
});
