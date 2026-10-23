<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Auth\BaseNotificationController;

class NotificationController extends BaseNotificationController
{
    protected function guardName(): string { return 'admin'; }
    protected function viewName(): string { return 'admin.notifications.index'; }
    protected function routePrefix(): string { return 'admin.'; }
}
