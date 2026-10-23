<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Auth\BaseNotificationController;

class NotificationController extends BaseNotificationController
{
    protected function guardName(): string { return 'pegawai'; }
    protected function viewName(): string { return 'pegawai.notifications'; }
    protected function routePrefix(): string { return 'pegawai.'; }
}
