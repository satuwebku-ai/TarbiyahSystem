<?php

namespace App\Http\Controllers\Pelajar;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('pelajar.dashboard');
    }
}
