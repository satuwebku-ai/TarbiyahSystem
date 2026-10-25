<?php

use App\Http\Controllers\Pegawai\AttendanceController;
use App\Http\Controllers\Pegawai\AuthController;
use App\Http\Controllers\Pegawai\HistoryController;
use App\Http\Controllers\Pegawai\NotificationController;
use App\Http\Controllers\Pegawai\PasswordResetController;
use App\Http\Controllers\Pegawai\ProfileController;
use App\Http\Controllers\Pegawai\PwaController;
use App\Http\Controllers\Pegawai\TwoFactorController;
use Illuminate\Support\Facades\Route;

// pegawai.namadomain.com — nama route berawalan "pegawai."
// Portal khusus Pegawai/Guru: akun = tabel admins yang dihubungkan ke data pegawai (Fase 4), guard "pegawai".
// PWA (aplikasi yang bisa dipasang di HP): publik, tanpa data pengguna.
Route::get('manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.sw');
Route::get('offline', [PwaController::class, 'offline'])->name('pwa.offline');

Route::redirect('check-in', '/');
Route::redirect('check-out', '/');
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
    Route::get('two-factor', [AuthController::class, 'showTwoFactor'])->name('two-factor.challenge');
    Route::post('two-factor', [AuthController::class, 'twoFactor'])->middleware('throttle:20,1')->name('two-factor.challenge.store');

    Route::get('forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.store');
});

Route::middleware(['auth', 'pegawai.session', 'two-factor.enrolled'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [AttendanceController::class, 'show'])->name('dashboard');
    Route::post('check-in', [AttendanceController::class, 'checkIn'])->middleware('throttle:20,1')->name('check-in');
    Route::post('check-out', [AttendanceController::class, 'checkOut'])->middleware('throttle:20,1')->name('check-out');
    Route::post('corrections', [AttendanceController::class, 'storeCorrection'])->middleware('throttle:10,1')->name('corrections.store');

    Route::get('riwayat', HistoryController::class)->name('history');

    Route::prefix('notifikasi')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('read-all', [NotificationController::class, 'readAll'])->name('read-all');
        Route::get('{notification}/open', [NotificationController::class, 'open'])->name('open');
    });

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');

    Route::post('profile/two-factor', [TwoFactorController::class, 'enable'])->name('profile.two-factor.enable');
    Route::post('profile/two-factor/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('profile.two-factor.confirm');
    Route::post('profile/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->middleware('throttle:10,1')->name('profile.two-factor.recovery-codes');
    Route::delete('profile/two-factor', [TwoFactorController::class, 'disable'])->middleware('throttle:10,1')->name('profile.two-factor.disable');
});
