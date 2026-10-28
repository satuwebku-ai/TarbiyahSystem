<?php

use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\EmployeeAttendanceController;
use App\Http\Controllers\Api\Mobile\NotificationController;
use App\Http\Controllers\Api\Mobile\PushTokenController;
use App\Http\Controllers\Api\Mobile\StudentAttendanceController;
use App\Http\Controllers\Api\Mobile\TwoFactorEnrollmentController;
use App\Http\Controllers\Api\Mobile\TwoFactorSettingsController;
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

    Route::put('push-token', [PushTokenController::class, 'store'])->name('push-token.store');
    Route::delete('push-token', [PushTokenController::class, 'destroy'])->name('push-token.destroy');

    // Pengaturan 2FA pegawai dari menu Profil > Keamanan akun (sudah masuk, memakai token).
    Route::middleware('mobile.auth:pegawai')->prefix('security/two-factor')->name('security.two-factor.')->group(function () {
        Route::get('/', [TwoFactorSettingsController::class, 'show'])->name('show');
        Route::post('setup', [TwoFactorSettingsController::class, 'setup'])->middleware('throttle:10,1')->name('setup');
        Route::post('confirm', [TwoFactorSettingsController::class, 'confirm'])->middleware('throttle:10,1')->name('confirm');
        Route::post('recovery-codes', [TwoFactorSettingsController::class, 'recoveryCodes'])->middleware('throttle:5,1')->name('recovery-codes');
        Route::post('disable', [TwoFactorSettingsController::class, 'disable'])->middleware('throttle:5,1')->name('disable');
    });

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
