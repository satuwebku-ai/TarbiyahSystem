<?php

use Illuminate\Support\Facades\Route;

// namadomain.com (halaman publik)
Route::get('/', fn () => view('web.coming-soon'))->name('home');

// Sementara: pintu masuk ke semua area login (pelajar, pegawai, pengelola).
Route::get('/demo', fn () => view('web.demo'))->name('demo');
