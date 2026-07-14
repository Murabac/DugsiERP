<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModulePlaceholderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
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
        Route::post('/students/{student}/guardians', [StudentController::class, 'storeGuardian'])->whereNumber('student')->name('students.guardians.store');

        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}', [StaffController::class, 'show'])->whereNumber('staff')->name('staff.show');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->whereNumber('staff')->name('staff.update');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/users', [SettingsController::class, 'storeUser'])->name('settings.users.store');
        Route::post('/settings/users/{user}/toggle', [SettingsController::class, 'toggleUser'])->whereNumber('user')->name('settings.users.toggle');
        Route::post('/settings/users/{user}/remove', [SettingsController::class, 'destroyUser'])->whereNumber('user')->name('settings.users.destroy');

        Route::post('/timetable/slots', [TimetableController::class, 'upsertSlot'])->name('timetable.upsert');
        Route::post('/timetable/slots/clear', [TimetableController::class, 'clearSlot'])->name('timetable.clear');
        Route::post('/timetable/slots/swap', [TimetableController::class, 'swapSlots'])->name('timetable.swap');
        Route::post('/timetable/generate', [TimetableController::class, 'generate'])->name('timetable.generate');

        Route::get('/notifications', fn () => app(ModulePlaceholderController::class)('Notifications', 'Week 9 — SMS/email templates and log.'))->name('notifications.index');
    });

    Route::middleware('role:admin,super_admin,teacher')->group(function () {
        Route::get('/classes', [ClassController::class, 'index'])->name('classes.index');
        Route::get('/classes/{schoolClass}/roster', [ClassController::class, 'roster'])->whereNumber('schoolClass')->name('classes.roster');
        Route::get('/students/{student}', [StudentController::class, 'show'])->whereNumber('student')->name('students.show');

        Route::get('/timetable', [TimetableController::class, 'index'])->name('timetable.index');
        Route::get('/timetable/print', [TimetableController::class, 'print'])->name('timetable.print');
        Route::get('/attendance', fn () => app(ModulePlaceholderController::class)('Attendance', 'Week 5 — marking, history, and print view.'))->name('attendance.index');
        Route::get('/grades', fn () => app(ModulePlaceholderController::class)('Grades', 'Week 6 — entry, boundaries, and reports.'))->name('grades.index');
    });

    Route::middleware('role:admin,super_admin,finance')->group(function () {
        Route::get('/finance/fees-dashboard', fn () => app(ModulePlaceholderController::class)('Fees Dashboard', 'Week 7 — finance KPIs and charts.'))->name('finance.fees-dashboard');
        Route::get('/finance/fee-collection', fn () => app(ModulePlaceholderController::class)('Fee Collection', 'Week 7 — invoices and payments.'))->name('finance.fee-collection');
        Route::get('/finance/expenses', fn () => app(ModulePlaceholderController::class)('Expenses', 'Finance expenses shell (confirm scope vs CONTEXT).'))->name('finance.expenses');
        Route::get('/finance/accounting', fn () => app(ModulePlaceholderController::class)('Accounting', 'Accounting shell (confirm scope vs CONTEXT).'))->name('finance.accounting');
        Route::get('/payroll', fn () => app(ModulePlaceholderController::class)('Payroll', 'Week 8 — payroll runs and payslips.'))->name('payroll.index');
        Route::get('/documents', fn () => app(ModulePlaceholderController::class)('Documents', 'Week 9 — report cards, certificates, receipts, ID cards.'))->name('documents.index');
        Route::get('/reports', fn () => app(ModulePlaceholderController::class)('Reports', 'Week 10 — attendance, grades, fees, payroll, enrollment.'))->name('reports.index');
    });
});
