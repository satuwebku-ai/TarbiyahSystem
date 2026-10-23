<?php

use App\Http\Controllers\Pelajar\AuthController;
use App\Http\Controllers\Pelajar\DashboardController;
use App\Http\Controllers\Pelajar\PasswordResetController;
use App\Http\Controllers\Pelajar\ProfileController;
use App\Http\Controllers\Pelajar\RegisterController;
use App\Http\Controllers\Auth\DemoLoginController;
use Illuminate\Support\Facades\Route;

// pelajar.namadomain.com — nama route berawalan "pelajar."

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
    Route::post('demo-login', [DemoLoginController::class, 'store'])->middleware('throttle:20,1')->defaults('guard', 'pelajar')->name('demo.login');

    if (config('domains.pelajar_registration')) {
        Route::get('register', [RegisterController::class, 'show'])->name('register');
        Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');
    }

    Route::get('forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
});
