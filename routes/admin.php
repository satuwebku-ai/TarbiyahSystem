<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AttendanceCorrectionController;
use App\Http\Controllers\Admin\AttendanceEntryController;
use App\Http\Controllers\Admin\AttendanceHolidayController;
use App\Http\Controllers\Admin\AttendancePolicyController;
use App\Http\Controllers\Admin\AttendanceProofController;
use App\Http\Controllers\Admin\AttendanceReportController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeAccountController;
use App\Http\Controllers\Admin\EmployeeAssignmentController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeDocumentController;
use App\Http\Controllers\Admin\EmployeeStatusController;
use App\Http\Controllers\Admin\FingerprintDeviceController;
use App\Http\Controllers\Admin\FingerprintLogController;
use App\Http\Controllers\Admin\FingerprintPinController;
use App\Http\Controllers\Admin\FirstAdminSetupController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OrganizationAccessController;
use App\Http\Controllers\Admin\OrganizationalAssignmentController;
use App\Http\Controllers\Admin\OrganizationalPositionController;
use App\Http\Controllers\Admin\OrganizationalUnitController;
use App\Http\Controllers\Admin\OrganizationalUnitTypeController;
use App\Http\Controllers\Admin\OrganizationDashboardController;
use App\Http\Controllers\Admin\PasswordResetController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\SecuritySettingsController;
use App\Http\Controllers\Admin\StudentAttendanceController;
use App\Http\Controllers\Admin\StudentAttendanceSettingController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\WorkActivityController;
use App\Http\Controllers\Admin\WorkProgramController;
use App\Http\Controllers\Auth\DemoLoginController;
use Illuminate\Support\Facades\Route;

// pengelola.namadomain.com — nama route berawalan "admin."

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
    Route::post('demo-login', [DemoLoginController::class, 'store'])->middleware('throttle:20,1')->defaults('guard', 'admin')->name('demo.login');
    Route::get('setup', [FirstAdminSetupController::class, 'show'])->name('setup');
    Route::post('setup', [FirstAdminSetupController::class, 'store'])->middleware('throttle:5,1')->name('setup.store');

    Route::get('forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.store');
});

Route::middleware(['auth', 'admin.session'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');

    // Kotak masuk notifikasi milik sendiri: cukup login, tanpa izin khusus (hanya menyentuh notifikasi pemilik).
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('read-all', [NotificationController::class, 'readAll'])->name('read-all');
        Route::get('{notification}/open', [NotificationController::class, 'open'])->name('open');
    });

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');

    Route::get('security', [SecuritySettingsController::class, 'index'])
        ->middleware('can:security.view')->name('security.index');
    Route::put('security', [SecuritySettingsController::class, 'update'])
        ->middleware('can:security.update')->name('security.update');
    Route::get('security/activity', [SecuritySettingsController::class, 'activity'])
        ->middleware('can:security.activity')->name('security.activity');

    Route::resource('roles', RoleController::class)->except('show');
    Route::resource('admins', AdminController::class)->except('show');

    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->middleware('can:employees.view')->name('index');
        Route::prefix('types')->name('types.')->middleware('can:employees.types.manage')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\EmployeeTypeController::class, 'index'])->name('index');
            Route::get('create', [\App\Http\Controllers\Admin\EmployeeTypeController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Admin\EmployeeTypeController::class, 'store'])->name('store');
            Route::get('{type}/edit', [\App\Http\Controllers\Admin\EmployeeTypeController::class, 'edit'])->name('edit');
            Route::put('{type}', [\App\Http\Controllers\Admin\EmployeeTypeController::class, 'update'])->name('update');
            Route::patch('{type}/toggle', [\App\Http\Controllers\Admin\EmployeeTypeController::class, 'toggle'])->name('toggle');
        });
        Route::get('create', [EmployeeController::class, 'create'])->middleware('can:employees.create')->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->middleware('can:employees.create')->name('store');
        Route::get('{employee}', [EmployeeController::class, 'show'])->middleware('can:employees.view')->name('show');
        Route::get('{employee}/edit', [EmployeeController::class, 'edit'])->middleware('can:employees.update')->name('edit');
        Route::put('{employee}', [EmployeeController::class, 'update'])->middleware('can:employees.update')->name('update');

        Route::post('{employee}/status', [EmployeeStatusController::class, 'store'])
            ->middleware('can:employees.status')->name('status.store');
        Route::put('{employee}/account', [EmployeeAccountController::class, 'update'])
            ->middleware('can:employees.link_account')->name('account.update');
        Route::post('{employee}/account', [EmployeeAccountController::class, 'store'])
            ->middleware('can:employees.link_account')->name('account.store');

        Route::middleware('can:employees.assignments.manage')->group(function () {
            Route::get('{employee}/assignments/create', [EmployeeAssignmentController::class, 'create'])->name('assignments.create');
            Route::post('{employee}/assignments', [EmployeeAssignmentController::class, 'store'])->name('assignments.store');
            Route::get('{employee}/assignments/{assignment}/edit', [EmployeeAssignmentController::class, 'edit'])->name('assignments.edit');
            Route::put('{employee}/assignments/{assignment}', [EmployeeAssignmentController::class, 'update'])->name('assignments.update');
            Route::patch('{employee}/assignments/{assignment}/end', [EmployeeAssignmentController::class, 'end'])->name('assignments.end');
        });

        Route::post('{employee}/documents', [EmployeeDocumentController::class, 'store'])
            ->middleware('can:employees.documents.manage')->name('documents.store');
        Route::get('{employee}/documents/{document}', [EmployeeDocumentController::class, 'download'])
            ->middleware('can:employees.documents.view')->name('documents.download');
        Route::delete('{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])
            ->middleware('can:employees.documents.manage')->name('documents.destroy');
    });

    Route::prefix('programs')->name('programs.')->group(function () {
        Route::get('/', [WorkProgramController::class, 'index'])->middleware('can:programs.view')->name('index');
        Route::get('create', [WorkProgramController::class, 'create'])->middleware('can:programs.manage')->name('create');
        Route::post('/', [WorkProgramController::class, 'store'])->middleware('can:programs.manage')->name('store');
        Route::get('{workProgram}', [WorkProgramController::class, 'show'])->middleware('can:programs.view')->name('show');
        Route::get('{workProgram}/edit', [WorkProgramController::class, 'edit'])->middleware('can:programs.manage')->name('edit');
        Route::put('{workProgram}', [WorkProgramController::class, 'update'])->middleware('can:programs.manage')->name('update');
        Route::post('{workProgram}/submit', [WorkProgramController::class, 'submit'])->middleware('can:programs.submit')->name('submit');
        Route::patch('{workProgram}/review', [WorkProgramController::class, 'review'])->middleware('can:programs.approve')->name('review');

        Route::post('{workProgram}/activities', [WorkActivityController::class, 'store'])->middleware('can:programs.manage')->name('activities.store');
        Route::patch('{workProgram}/activities/{activity}/status', [WorkActivityController::class, 'updateStatus'])->middleware('can:programs.manage')->name('activities.status');
        Route::post('{workProgram}/activities/{activity}/budget-items', [WorkActivityController::class, 'storeBudgetItem'])->middleware('can:programs.manage')->name('activities.budget-items.store');
        Route::post('{workProgram}/activities/{activity}/participants', [WorkActivityController::class, 'storeParticipant'])->middleware('can:programs.manage')->name('activities.participants.store');
        Route::post('{workProgram}/activities/{activity}/report', [WorkActivityController::class, 'saveReport'])->middleware('can:programs.manage')->name('activities.report.save');
        Route::post('{workProgram}/activities/{activity}/report/submit', [WorkActivityController::class, 'submitReport'])->middleware('can:programs.submit')->name('activities.report.submit');
        Route::patch('{workProgram}/activities/{activity}/report/review', [WorkActivityController::class, 'reviewReport'])->middleware('can:programs.approve')->name('activities.report.review');
        Route::post('{workProgram}/activities/{activity}/documents', [WorkActivityController::class, 'uploadDocument'])->middleware('can:programs.manage')->name('activities.documents.store');
        Route::get('{workProgram}/activities/{activity}/documents/{document}', [WorkActivityController::class, 'downloadDocument'])->middleware('can:programs.view')->name('activities.documents.download');
    });

    Route::prefix('academic')->name('academic.')->group(function () {
        Route::prefix('schools')->name('schools.')->group(function () {
            Route::get('/', [SchoolController::class, 'index'])
                ->middleware('can:education.schools.view')->name('index');
            Route::get('create', [SchoolController::class, 'create'])
                ->middleware('can:education.schools.manage')->name('create');
            Route::post('/', [SchoolController::class, 'store'])
                ->middleware('can:education.schools.manage')->name('store');
            Route::get('{school}/edit', [SchoolController::class, 'edit'])
                ->middleware('can:education.schools.manage')->name('edit');
            Route::put('{school}', [SchoolController::class, 'update'])
                ->middleware('can:education.schools.manage')->name('update');
            Route::get('{school}', [SchoolController::class, 'show'])
                ->middleware('can:education.schools.view')->name('show');

            Route::post('{school}/academic-years', [SchoolController::class, 'storeAcademicYear'])
                ->middleware('can:education.academic.manage')->name('academic-years.store');
            Route::patch('{school}/academic-years/{academicYear}/activate', [SchoolController::class, 'activateAcademicYear'])
                ->middleware('can:education.academic.manage')->name('academic-years.activate');
            Route::post('{school}/classrooms', [SchoolController::class, 'storeClassroom'])
                ->middleware('can:education.academic.manage')->name('classrooms.store');
        });

        Route::prefix('students')->name('students.')->group(function () {
            Route::get('/', [StudentController::class, 'index'])
                ->middleware('can:education.students.view')->name('index');
            Route::get('create', [StudentController::class, 'create'])
                ->middleware('can:education.students.manage')->name('create');
            Route::post('/', [StudentController::class, 'store'])
                ->middleware('can:education.students.manage')->name('store');
            Route::get('{student}/edit', [StudentController::class, 'edit'])
                ->middleware('can:education.students.manage')->name('edit');
            Route::put('{student}', [StudentController::class, 'update'])
                ->middleware('can:education.students.manage')->name('update');
            Route::get('{student}', [StudentController::class, 'show'])
                ->middleware('can:education.students.view')->name('show');
            Route::post('{student}/guardians', [StudentController::class, 'storeGuardian'])
                ->middleware('can:education.students.manage')->name('guardians.store');
            Route::post('{student}/enrollments', [StudentController::class, 'storeEnrollment'])
                ->middleware('can:education.students.manage')->name('enrollments.store');
            Route::patch('{student}/enrollments/{enrollment}/status', [StudentController::class, 'updateEnrollmentStatus'])
                ->middleware('can:education.students.manage')->name('enrollments.status');
            Route::post('{student}/enrollments/{enrollment}/placements', [StudentController::class, 'storePlacement'])
                ->middleware('can:education.students.manage')->name('placements.store');
        });

        Route::prefix('student-attendance')->name('student-attendance.')->group(function () {
            Route::get('/', [StudentAttendanceController::class, 'index'])
                ->middleware('can:education.attendance.manage')->name('index');
            Route::post('/', [StudentAttendanceController::class, 'store'])
                ->middleware('can:education.attendance.manage')->name('store');
            Route::get('report', [StudentAttendanceController::class, 'report'])
                ->middleware('can:education.attendance.view')->name('report');
        });

        Route::prefix('student-attendance/settings')->name('student-attendance.settings.')
            ->middleware('can:education.attendance.settings')->group(function () {
                Route::get('/', [StudentAttendanceSettingController::class, 'index'])->name('index');
                Route::get('general', [StudentAttendanceSettingController::class, 'editGeneral'])->name('general.edit');
                Route::put('general', [StudentAttendanceSettingController::class, 'updateGeneral'])->name('general.update');
                Route::get('schools/{school}', [StudentAttendanceSettingController::class, 'editSchool'])->name('school.edit');
                Route::put('schools/{school}', [StudentAttendanceSettingController::class, 'updateSchool'])->name('school.update');
                Route::delete('schools/{school}', [StudentAttendanceSettingController::class, 'resetSchool'])->name('school.reset');
            });

        Route::prefix('fingerprint')->name('fingerprint.')->group(function () {
            Route::get('devices', [FingerprintDeviceController::class, 'index'])
                ->middleware('can:education.fingerprint.view')->name('devices.index');
            Route::middleware('can:education.fingerprint.manage')->group(function () {
                Route::get('devices/create', [FingerprintDeviceController::class, 'create'])->name('devices.create');
                Route::post('devices', [FingerprintDeviceController::class, 'store'])->name('devices.store');
                Route::get('devices/{device}/edit', [FingerprintDeviceController::class, 'edit'])->name('devices.edit');
                Route::put('devices/{device}', [FingerprintDeviceController::class, 'update'])->name('devices.update');
                Route::patch('devices/{device}/toggle', [FingerprintDeviceController::class, 'toggle'])->name('devices.toggle');
                Route::delete('devices/{device}', [FingerprintDeviceController::class, 'destroy'])->name('devices.destroy');

                Route::get('pins', [FingerprintPinController::class, 'index'])->name('pins.index');
                Route::put('pins', [FingerprintPinController::class, 'update'])->name('pins.update');
                Route::post('logs/reprocess', [FingerprintLogController::class, 'reprocess'])->name('logs.reprocess');
            });
            Route::get('logs', [FingerprintLogController::class, 'index'])
                ->middleware('can:education.fingerprint.view')->name('logs.index');
        });
    });

    Route::prefix('reports')->name('reports.')->middleware('can:reports.view')->group(function () {
        $types = implode('|', array_keys(\App\Services\ReportService::TYPES));

        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('{report}', [ReportController::class, 'show'])->where('report', $types)->name('show');
        Route::get('{report}/export', [ReportController::class, 'export'])
            ->where('report', $types)->middleware('can:reports.export')->name('export');
        Route::get('{report}/print', [ReportController::class, 'printView'])
            ->where('report', $types)->middleware('can:reports.export')->name('print');
    });

    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::middleware('can:attendance.view')->group(function () {
            Route::get('daily', [AttendanceReportController::class, 'daily'])->name('daily');
            Route::get('monthly', [AttendanceReportController::class, 'monthly'])->name('monthly');
            Route::get('records/{record}/photo/{type}', [AttendanceProofController::class, 'photo'])->whereNumber('record')->name('photo');
        });

        Route::middleware('can:attendance.corrections.review')->group(function () {
            Route::get('corrections', [AttendanceCorrectionController::class, 'index'])->name('corrections.index');
            Route::patch('corrections/{correction}/approve', [AttendanceCorrectionController::class, 'approve'])->name('corrections.approve');
            Route::patch('corrections/{correction}/reject', [AttendanceCorrectionController::class, 'reject'])->name('corrections.reject');
        });

        Route::middleware('can:attendance.records.manage')->group(function () {
            Route::get('entries', [AttendanceEntryController::class, 'index'])->name('entries.index');
            Route::post('entries', [AttendanceEntryController::class, 'store'])->name('entries.store');
            Route::delete('entries/{record}', [AttendanceEntryController::class, 'destroy'])->name('entries.destroy');
        });

        Route::middleware('can:attendance.policies.manage')->group(function () {
            Route::get('policies', [AttendancePolicyController::class, 'index'])->name('policies.index');
            Route::get('policies/create', [AttendancePolicyController::class, 'create'])->name('policies.create');
            Route::post('policies', [AttendancePolicyController::class, 'store'])->name('policies.store');
            Route::get('policies/{policy}/edit', [AttendancePolicyController::class, 'edit'])->name('policies.edit');
            Route::put('policies/{policy}', [AttendancePolicyController::class, 'update'])->name('policies.update');
            Route::patch('policies/{policy}/toggle', [AttendancePolicyController::class, 'toggle'])->name('policies.toggle');
        });

        Route::middleware('can:attendance.holidays.manage')->group(function () {
            Route::get('holidays', [AttendanceHolidayController::class, 'index'])->name('holidays.index');
            Route::post('holidays', [AttendanceHolidayController::class, 'store'])->name('holidays.store');
            Route::delete('holidays/{holiday}', [AttendanceHolidayController::class, 'destroy'])->name('holidays.destroy');
        });
    });

    Route::prefix('organization')->name('organization.')->group(function () {
        Route::get('/', OrganizationDashboardController::class)
            ->middleware('can:organization.view')->name('index');

        Route::get('units', [OrganizationalUnitController::class, 'index'])
            ->middleware('can:organization.view')->name('units.index');
        Route::get('units/create', [OrganizationalUnitController::class, 'create'])
            ->middleware('can:organization.units.manage')->name('units.create');
        Route::post('units', [OrganizationalUnitController::class, 'store'])
            ->middleware('can:organization.units.manage')->name('units.store');
        Route::get('units/{unit}', [OrganizationalUnitController::class, 'show'])
            ->middleware('can:organization.view')->name('units.show');
        Route::get('units/{unit}/edit', [OrganizationalUnitController::class, 'edit'])
            ->middleware('can:organization.units.manage')->name('units.edit');
        Route::put('units/{unit}', [OrganizationalUnitController::class, 'update'])
            ->middleware('can:organization.units.manage')->name('units.update');

        Route::get('unit-types', [OrganizationalUnitTypeController::class, 'index'])
            ->middleware('can:organization.types.manage')->name('unit-types.index');
        Route::get('unit-types/create', [OrganizationalUnitTypeController::class, 'create'])
            ->middleware('can:organization.types.manage')->name('unit-types.create');
        Route::post('unit-types', [OrganizationalUnitTypeController::class, 'store'])
            ->middleware('can:organization.types.manage')->name('unit-types.store');
        Route::get('unit-types/{unitType}/edit', [OrganizationalUnitTypeController::class, 'edit'])
            ->middleware('can:organization.types.manage')->name('unit-types.edit');
        Route::put('unit-types/{unitType}', [OrganizationalUnitTypeController::class, 'update'])
            ->middleware('can:organization.types.manage')->name('unit-types.update');
        Route::patch('unit-types/{unitType}/toggle', [OrganizationalUnitTypeController::class, 'toggle'])
            ->middleware('can:organization.types.manage')->name('unit-types.toggle');

        Route::get('positions', [OrganizationalPositionController::class, 'index'])
            ->middleware('can:organization.positions.manage')->name('positions.index');
        Route::get('positions/create', [OrganizationalPositionController::class, 'create'])
            ->middleware('can:organization.positions.manage')->name('positions.create');
        Route::post('positions', [OrganizationalPositionController::class, 'store'])
            ->middleware('can:organization.positions.manage')->name('positions.store');
        Route::get('positions/{position}/edit', [OrganizationalPositionController::class, 'edit'])
            ->middleware('can:organization.positions.manage')->name('positions.edit');
        Route::put('positions/{position}', [OrganizationalPositionController::class, 'update'])
            ->middleware('can:organization.positions.manage')->name('positions.update');
        Route::patch('positions/{position}/toggle', [OrganizationalPositionController::class, 'toggle'])
            ->middleware('can:organization.positions.manage')->name('positions.toggle');

        Route::get('assignments', [OrganizationalAssignmentController::class, 'index'])
            ->middleware('can:organization.assignments.manage')->name('assignments.index');
        Route::get('assignments/create', [OrganizationalAssignmentController::class, 'create'])
            ->middleware('can:organization.assignments.manage')->name('assignments.create');
        Route::post('assignments', [OrganizationalAssignmentController::class, 'store'])
            ->middleware('can:organization.assignments.manage')->name('assignments.store');
        Route::get('assignments/{assignment}/edit', [OrganizationalAssignmentController::class, 'edit'])
            ->middleware('can:organization.assignments.manage')->name('assignments.edit');
        Route::put('assignments/{assignment}', [OrganizationalAssignmentController::class, 'update'])
            ->middleware('can:organization.assignments.manage')->name('assignments.update');
        Route::patch('assignments/{assignment}/end', [OrganizationalAssignmentController::class, 'end'])
            ->middleware('can:organization.assignments.manage')->name('assignments.end');

        Route::get('access', [OrganizationAccessController::class, 'index'])
            ->middleware('can:organization.access.manage')->name('access.index');
        Route::put('access', [OrganizationAccessController::class, 'update'])
            ->middleware('can:organization.access.manage')->name('access.update');
    });
});
