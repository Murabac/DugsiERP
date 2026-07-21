<?php

namespace App\Support;

/**
 * Fixed permission keys Super Admin can tick when building roles.
 * Keep in sync with route middleware and Navigation / Modules filters.
 */
class PermissionCatalog
{
    /**
     * @return array<string, array<string, string>> group label => [key => label]
     */
    public static function grouped(): array
    {
        return [
            'Overview' => [
                'overview.view' => 'Dashboard',
            ],
            'Classes' => [
                'classes.view' => 'View classes & rosters',
                'classes.manage' => 'Create / edit classes & waitlist',
            ],
            'Students' => [
                'students.view' => 'View student profiles',
                'students.manage' => 'Create / edit students & guardians',
            ],
            'Staff' => [
                'staff.view' => 'View staff directory',
                'staff.manage' => 'Create / edit staff & mark attendance',
            ],
            'Timetable' => [
                'timetable.view' => 'View timetable',
                'timetable.manage' => 'Edit / generate timetable',
            ],
            'Attendance' => [
                'attendance.view' => 'View history, week sheet & print',
                'attendance.mark' => 'Mark student attendance',
            ],
            'Grades' => [
                'grades.view' => 'View grades',
                'grades.enter' => 'Enter marks',
            ],
            'Transport' => [
                'transport.view' => 'View buses & assignments',
                'transport.manage' => 'Manage buses & riders',
            ],
            'Fees' => [
                'fees.view' => 'Fees dashboard',
                'fees.collect' => 'Collect payments',
                'fees.generate' => 'Generate monthly invoices',
            ],
            'Expenses' => [
                'expenses.view' => 'View expenses',
                'expenses.manage' => 'Add / edit expenses',
            ],
            'Payroll' => [
                'payroll.view' => 'View payroll runs',
                'payroll.run' => 'Generate payroll',
            ],
            'Documents' => [
                'documents.view' => 'View issued documents',
                'documents.issue' => 'Issue documents',
            ],
            'Notifications' => [
                'notifications.view' => 'View templates & log',
                'notifications.manage' => 'Edit templates & send SMS',
            ],
            'Reports' => [
                'reports.view' => 'Reports hub',
                'reports.academic' => 'Attendance, academic & enrollment',
                'reports.finance' => 'Fees & payroll reports',
            ],
            'Settings' => [
                'settings.manage' => 'School settings & users',
            ],
            'Roles' => [
                'roles.manage' => 'Create & edit roles (Super Admin)',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allKeys(): array
    {
        $keys = [];
        foreach (self::grouped() as $perms) {
            foreach (array_keys($perms) as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, string> key => label
     */
    public static function labels(): array
    {
        $labels = [];
        foreach (self::grouped() as $perms) {
            foreach ($perms as $key => $label) {
                $labels[$key] = $label;
            }
        }

        return $labels;
    }

    /**
     * Default permission keys for seeded system roles.
     *
     * @return list<string>
     */
    public static function defaultsFor(string $roleKey): array
    {
        return match ($roleKey) {
            'super_admin' => self::allKeys(),
            'admin' => array_values(array_filter(
                self::allKeys(),
                fn (string $key) => $key !== 'roles.manage'
            )),
            'finance' => [
                'overview.view',
                'students.view',
                'transport.view',
                'transport.manage',
                'fees.view',
                'fees.collect',
                'fees.generate',
                'expenses.view',
                'expenses.manage',
                'payroll.view',
                'payroll.run',
                'documents.view',
                'documents.issue',
                'reports.view',
                'reports.finance',
            ],
            'teacher' => [
                'overview.view',
                'classes.view',
                'students.view',
                'timetable.view',
                'attendance.view',
                'attendance.mark',
                'grades.view',
                'grades.enter',
            ],
            default => [],
        };
    }

    /**
     * Permission required to show a named route in nav / apps (null = always).
     */
    public static function forRoute(string $route): ?string
    {
        return match ($route) {
            'modules.home' => null,
            'dashboard' => 'overview.view',
            'classes.index', 'classes.roster', 'classes.manage' => 'classes.view',
            'staff.index', 'staff.show' => 'staff.view',
            'staff-attendance.index', 'staff-attendance.print', 'staff-attendance.store' => 'staff.manage',
            'staff-attendance.history', 'staff-attendance.history.print' => 'staff.view',
            'timetable.index', 'timetable.requirements', 'timetable.print' => 'timetable.view',
            'attendance.index', 'attendance.history', 'attendance.week-sheet', 'attendance.print' => 'attendance.view',
            'grades.index', 'grades.report', 'grades.print' => 'grades.view',
            'transport.index', 'transport.buses.show', 'transport.assignments.index' => 'transport.view',
            'finance.fees-dashboard', 'finance.fee-collection' => 'fees.view',
            'finance.expenses' => 'expenses.view',
            'payroll.index', 'payroll.show', 'payroll.payslip', 'payroll.generate' => 'payroll.view',
            'documents.index', 'documents.preview', 'documents.print' => 'documents.view',
            'notifications.index' => 'notifications.view',
            'reports.index' => 'reports.view',
            'reports.attendance', 'reports.academic', 'reports.enrollment' => 'reports.academic',
            'reports.fees',
            'reports.fees.collection',
            'reports.fees.collection.print',
            'reports.fees.print',
            'reports.fees.students-by-form',
            'reports.fees.students-by-form.print',
            'reports.fees.income',
            'reports.fees.income.print',
            'reports.fees.expenses',
            'reports.fees.expenses.print',
            'reports.fees.net-income',
            'reports.fees.net-income.print',
            'reports.fees.monthly-close',
            'reports.fees.monthly-close.print',
            'reports.payroll',
            'reports.payroll.print' => 'reports.finance',
            'settings.index', 'settings.weekly-periods', 'settings.day-structure' => 'settings.manage',
            default => null,
        };
    }
}
