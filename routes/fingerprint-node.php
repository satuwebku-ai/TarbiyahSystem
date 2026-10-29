<?php

use App\Http\Controllers\Api\FingerprintNodeController;
use Illuminate\Support\Facades\Route;

/*
| API aplikasi Node fingerprint (prefix api/fingerprint-node, dimuat di bootstrap/app.php).
| Tanpa sesi/CSRF. Auth: Basic (username:password) + header X-Node-Token.
*/
Route::middleware(['fingerprint.node', 'throttle:600,1'])->group(function () {
    Route::post('connect', [FingerprintNodeController::class, 'connect'])->name('connect');
    Route::post('punches', [FingerprintNodeController::class, 'punches'])->name('punches');
});
