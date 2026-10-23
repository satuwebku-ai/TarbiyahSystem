<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;
use App\Services\AppSettings;
use App\Services\OrganizationSetup;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AppSettings::class);
    }

    public function boot(): void
    {
        // Pengaturan dari menu Pengaturan Aplikasi (tabel app_settings) menimpa nilai .env/config. Aman bila tabel belum ada.
        $this->app->make(AppSettings::class)->apply();

        // Paginasi memakai markup Bootstrap 5 (default Laravel = Tailwind, SVG-nya jadi raksasa tanpa Tailwind).
        Paginator::useBootstrapFive();

        // permission -> Gate: can:roles.view, $this->authorize(...), @can(...)
        Gate::before(function ($user, string $ability) {
            if (! method_exists($user, 'hasPermission')) {
                return null;
            }
            if ($user->isSuperAdmin()) {
                return true;
            }
            $impliedPermissions = [
                'organization.view' => [
                    'organization.units.manage',
                    'organization.types.manage',
                    'organization.positions.manage',
                    'organization.assignments.manage',
                    'organization.access.manage',
                ],
                'education.schools.view' => [
                    'education.schools.manage',
                    'education.academic.manage',
                ],
                'education.students.view' => [
                    'education.students.manage',
                ],
                'education.attendance.view' => [
                    'education.attendance.manage',
                ],
                'education.fingerprint.view' => [
                    'education.fingerprint.manage',
                ],
                'reports.view' => [
                    'reports.export',
                ],
            ];
            if (isset($impliedPermissions[$ability])
                && collect($impliedPermissions[$ability])->contains(fn (string $permission) => $user->hasPermission($permission))) {
                return true;
            }

            return $user->hasPermission($ability) ? true : null;
        });

        // Stepper alur struktur organisasi (Jenis unit -> Unit -> Jabatan -> Penugasan).
        View::composer('admin.organization.partials.setup-steps', function ($view) {
            $admin = auth('admin')->user();
            $view->with('setupSteps', $admin ? app(OrganizationSetup::class)->steps($admin) : []);
        });

        // Kegagalan pekerjaan antrean (notifikasi, email) dicatat jelas; detailnya tersimpan di tabel failed_jobs
        // dan dapat dicoba ulang dengan `php artisan queue:retry all`.
        Queue::failing(function (JobFailed $event) {
            Log::error('Pekerjaan antrean gagal.', [
                'job' => $event->job->resolveName(),
                'connection' => $event->connectionName,
                'error' => $event->exception->getMessage(),
            ]);
        });

        // Link reset password mengarah ke subdomain yang benar.
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $route = match (true) {
                $user instanceof Admin => 'admin.password.reset',
                $user instanceof User => 'pegawai.password.reset',
                default => 'pelajar.password.reset',
            };

            return route($route, ['token' => $token, 'email' => $user->getEmailForPasswordReset()]);
        });
    }
}