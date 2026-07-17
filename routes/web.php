<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\ModulePlaceholderController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffAttendanceController;
use App\Http\Controllers\StaffCheckinController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TransportAssignmentController;
use App\Http\Controllers\TransportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

// Personal staff check-in (phone biometric) — no login; school Wi-Fi gated in controller
Route::middleware(['throttle:30,1'])->prefix('a/{token}')->where(['token' => '[A-Za-z0-9]{32,}'])->group(function () {
    Route::get('/', [StaffCheckinController::class, 'show'])->name('staff-checkin.show');
    Route::post('/webauthn/register/options', [StaffCheckinController::class, 'registerOptions'])->name('staff-checkin.register.options');
    Route::post('/webauthn/register/verify', [StaffCheckinController::class, 'registerVerify'])->name('staff-checkin.register.verify');
    Route::post('/webauthn/login/options', [StaffCheckinController::class, 'loginOptions'])->name('staff-checkin.login.options');
    Route::post('/webauthn/login/verify', [StaffCheckinController::class, 'loginVerify'])->name('staff-checkin.login.verify');
    Route::post('/local-punch', [StaffCheckinController::class, 'localPunch'])->name('staff-checkin.local-punch');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:admin,super_admin')->group(function () {
        Route::get('/classes/manage', [ClassController::class, 'manage'])->name('classes.manage');
        Route::post('/classes', [ClassController::class, 'store'])->name('classes.store');
        Route::put('/classes/{schoolClass}', [ClassController::class, 'update'])->whereNumber('schoolClass')->name('classes.update');
        Route::post('/classes/{schoolClass}/archive', [ClassController::class, 'archive'])->whereNumber('schoolClass')->name('classes.archive');
        Route::post('/classes/{schoolClass}/waitlist/{waitlist}/enroll', [ClassController::class, 'enrollFromWaitlist'])
            ->whereNumber(['schoolClass', 'waitlist'])
            ->name('classes.waitlist.enroll');

        Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
        Route::post('/students', [StudentController::class, 'store'])->name('students.store');
        Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->whereNumber('student')->name('students.edit');
        Route::put('/students/{student}', [StudentController::class, 'update'])->whereNumber('student')->name('students.update');
        Route::post('/students/{student}/guardians', [StudentController::class, 'storeGuardian'])->whereNumber('student')->name('students.guardians.store');
        Route::put('/students/{student}/guardians/{guardian}', [StudentController::class, 'updateGuardian'])
            ->whereNumber(['student', 'guardian'])
            ->name('students.guardians.update');
        Route::delete('/students/{student}/guardians/{guardian}', [StudentController::class, 'destroyGuardian'])
            ->whereNumber(['student', 'guardian'])
            ->name('students.guardians.destroy');
        Route::post('/students/{student}/need-based', [StudentController::class, 'updateNeedBased'])->whereNumber('student')->name('students.need-based');

        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}', [StaffController::class, 'show'])->whereNumber('staff')->name('staff.show');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->whereNumber('staff')->name('staff.update');
        Route::post('/staff/{staff}/checkin-link', [StaffAttendanceController::class, 'regenerateLink'])->whereNumber('staff')->name('staff.checkin-link');
        Route::post('/staff/{staff}/reset-biometric', [StaffAttendanceController::class, 'resetBiometric'])->whereNumber('staff')->name('staff.reset-biometric');

        Route::get('/staff-attendance', [StaffAttendanceController::class, 'index'])->name('staff-attendance.index');
        Route::post('/staff-attendance', [StaffAttendanceController::class, 'store'])->name('staff-attendance.store');
        Route::get('/staff-attendance/history', [StaffAttendanceController::class, 'history'])->name('staff-attendance.history');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/users', [SettingsController::class, 'storeUser'])->name('settings.users.store');
        Route::post('/settings/users/{user}/toggle', [SettingsController::class, 'toggleUser'])->whereNumber('user')->name('settings.users.toggle');
        Route::post('/settings/users/{user}/remove', [SettingsController::class, 'destroyUser'])->whereNumber('user')->name('settings.users.destroy');
        Route::post('/settings/grade-edit-window', [SettingsController::class, 'updateGradeEditWindow'])->name('settings.grade-edit-window');
        Route::post('/settings/school-profile', [SettingsController::class, 'updateSchoolProfile'])->name('settings.school-profile');
        Route::post('/settings/fee-settings', [SettingsController::class, 'updateFeeSettings'])->name('settings.fee-settings');
        Route::post('/settings/staff-attendance', [SettingsController::class, 'updateStaffAttendance'])->name('settings.staff-attendance');

        Route::post('/timetable/slots', [TimetableController::class, 'upsertSlot'])->name('timetable.upsert');
        Route::post('/timetable/slots/clear', [TimetableController::class, 'clearSlot'])->name('timetable.clear');
        Route::post('/timetable/slots/swap', [TimetableController::class, 'swapSlots'])->name('timetable.swap');
        Route::post('/timetable/generate', [TimetableController::class, 'generate'])->name('timetable.generate');

        Route::post('/grades/boundaries', [GradeController::class, 'updateBoundaries'])->name('grades.boundaries.update');
        Route::get('/grades/boundaries', [GradeController::class, 'boundaries'])->name('grades.boundaries');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/templates/{template}', [NotificationController::class, 'updateTemplate'])
            ->whereNumber('template')
            ->name('notifications.templates.update');
        Route::post('/notifications/fee-reminder', [NotificationController::class, 'sendFeeReminder'])
            ->middleware('throttle:10,1')
            ->name('notifications.fee-reminder');

        Route::get('/reports/attendance', [ReportController::class, 'attendance'])->name('reports.attendance');
        Route::get('/reports/academic', [ReportController::class, 'academic'])->name('reports.academic');
        Route::get('/reports/enrollment', [ReportController::class, 'enrollment'])->name('reports.enrollment');
    });

    Route::middleware('role:admin,super_admin,teacher,finance')->group(function () {
        Route::get('/students/{student}', [StudentController::class, 'show'])->whereNumber('student')->name('students.show');
    });

    Route::middleware('role:admin,super_admin,teacher')->group(function () {
        Route::get('/classes', [ClassController::class, 'index'])->name('classes.index');
        Route::get('/classes/{schoolClass}/roster', [ClassController::class, 'roster'])->whereNumber('schoolClass')->name('classes.roster');
        Route::get('/students/by-parent', [StudentController::class, 'byParent'])->name('students.by-parent');

        Route::get('/timetable', [TimetableController::class, 'index'])->name('timetable.index');
        Route::get('/timetable/print', [TimetableController::class, 'print'])->name('timetable.print');

        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::get('/attendance/history', [AttendanceController::class, 'history'])->name('attendance.history');
        Route::get('/attendance/print', [AttendanceController::class, 'print'])->name('attendance.print');

        Route::get('/grades', [GradeController::class, 'index'])->name('grades.index');
        Route::post('/grades', [GradeController::class, 'store'])->name('grades.store');
        Route::get('/grades/report', [GradeController::class, 'report'])->name('grades.report');
        Route::get('/grades/print', [GradeController::class, 'print'])->name('grades.print');
    });

    Route::middleware('role:admin,super_admin,finance')->group(function () {
        Route::get('/finance/fees-dashboard', [FinanceController::class, 'dashboard'])->name('finance.fees-dashboard');
        Route::get('/finance/fee-collection', [FinanceController::class, 'collection'])->name('finance.fee-collection');
        Route::post('/finance/fee-collection/generate', [FinanceController::class, 'generate'])->name('finance.fee-collection.generate');
        Route::post('/finance/payments', [FinanceController::class, 'storePayment'])->name('finance.payments.store');
        Route::get('/finance/payments/{payment}/receipt', [FinanceController::class, 'receipt'])->whereNumber('payment')->name('finance.payments.receipt');
        Route::get('/finance/expenses', fn () => app(ModulePlaceholderController::class)(
            'Expenses',
            'Expense tracking is reserved for a later release. Confirm scope with the school before building.'
        ))->name('finance.expenses');
        Route::get('/finance/accounting', fn () => app(ModulePlaceholderController::class)(
            'Accounting',
            'General ledger / accounting is reserved for a later release. Confirm scope with the school before building.'
        ))->name('finance.accounting');
        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::get('/payroll/generate', [PayrollController::class, 'create'])->name('payroll.generate');
        Route::post('/payroll/generate', [PayrollController::class, 'store'])->name('payroll.generate.store');
        Route::get('/payroll/{payrollRun}', [PayrollController::class, 'show'])->whereNumber('payrollRun')->name('payroll.show');
        Route::get('/payroll/{payrollRun}/payslips/{payrollItem}', [PayrollController::class, 'payslip'])
            ->whereNumber(['payrollRun', 'payrollItem'])
            ->name('payroll.payslip');
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/preview', [DocumentController::class, 'preview'])->name('documents.preview');
        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('/documents/{document}/print', [DocumentController::class, 'print'])
            ->whereNumber('document')
            ->name('documents.print');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/fees', [ReportController::class, 'fees'])->name('reports.fees');
        Route::get('/reports/payroll', [ReportController::class, 'payroll'])->name('reports.payroll');

        Route::get('/transport', [TransportController::class, 'index'])->name('transport.index');
        Route::get('/transport/buses/create', [TransportController::class, 'create'])->name('transport.buses.create');
        Route::post('/transport/buses', [TransportController::class, 'store'])->name('transport.buses.store');
        Route::post('/transport/drivers', [TransportController::class, 'storeDriver'])->name('transport.drivers.store');
        Route::get('/transport/buses/{route}', [TransportController::class, 'show'])->whereNumber('route')->name('transport.buses.show');
        Route::get('/transport/buses/{route}/edit', [TransportController::class, 'edit'])->whereNumber('route')->name('transport.buses.edit');
        Route::put('/transport/buses/{route}', [TransportController::class, 'update'])->whereNumber('route')->name('transport.buses.update');
        Route::get('/transport/buses/{route}/print', [TransportController::class, 'print'])->whereNumber('route')->name('transport.buses.print');
        Route::get('/transport/assignments', [TransportAssignmentController::class, 'index'])->name('transport.assignments.index');
        Route::post('/transport/assignments', [TransportAssignmentController::class, 'store'])->name('transport.assignments.store');
        Route::put('/transport/assignments/{assignment}', [TransportAssignmentController::class, 'update'])->whereNumber('assignment')->name('transport.assignments.update');
        Route::post('/transport/assignments/{assignment}/end', [TransportAssignmentController::class, 'end'])->whereNumber('assignment')->name('transport.assignments.end');
    });
});
