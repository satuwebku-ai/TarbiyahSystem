<?php

use App\Http\Controllers\Fingerprint\IclockController;
use Illuminate\Support\Facades\Route;

/*
| Endpoint push mesin fingerprint (ADMS/iclock). Dimuat TANPA grup middleware "web":
| mesin bukan browser, jadi tidak ada sesi, cookie, maupun CSRF. Tidak dibatasi domain
| supaya bisa dijangkau lewat IP/host apa pun yang diisikan di mesin.
*/
Route::middleware('throttle:600,1')->prefix('iclock')->group(function () {
    Route::get('cdata', [IclockController::class, 'handshake']);
    Route::post('cdata', [IclockController::class, 'receive']);
    Route::get('getrequest', [IclockController::class, 'getRequest']);
    Route::post('devicecmd', [IclockController::class, 'deviceCmd']);
});
