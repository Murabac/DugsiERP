<?php

namespace App\Support;

use App\Models\User;
use App\Support\StaffAttendancePunch;

/**
 * Odoo-style module catalog for the post-login home grid.
 * Keep in sync with {@see Navigation} visibility rules.
 */
class Modules
{
    /**
     * @return list<array{key: string, label: string, description: string, icon: string, route: string, tone: string, permission?: string}>
     */
    public static function for(User $user): array
    {
        $modules = array_values(array_filter(
            self::allModules($user),
            function (array $module) use ($user): bool {
                $any = $module['any_permissions'] ?? null;
                if (is_array($any) && $any !== []) {
                    foreach ($any as $perm) {
                        if ($user->hasPermission($perm)) {
                            return true;
                        }
                    }

                    return false;
                }

                $perm = $module['permission'] ?? null;

                return $perm === null || $user->hasPermission($perm);
            }
        ));

        return self::withStaffCheckinTab($modules, $user);
    }

    /**
     * @return list<array{key: string, label: string, description: string, icon: string, route: string, tone: string, permission?: string, any_permissions?: list<string>}>
     */
    private static function allModules(User $user): array
    {
        $financeRoute = self::financeLandingRoute($user);

        $modules = [
            self::tile('overview', 'Overview', 'School snapshot and quick actions', 'home', 'dashboard', 'navy', 'overview.view'),
            self::tile('classes', 'Classes', 'Rosters, waitlist, and class setup', 'layers', 'classes.index', 'sky', 'classes.view'),
            self::tile('staff', 'Staff', 'Employees, logins, and salaries', 'briefcase', 'staff.index', 'indigo', 'staff.view'),
            self::tile('timetable', 'Timetable', 'Weekly periods and room schedule', 'calendar', 'timetable.index', 'teal', 'timetable.view'),
            self::tile('attendance', 'Attendance', 'Student and staff daily marking', 'check-circle', 'attendance.index', 'emerald', 'attendance.view'),
            self::tile('grades', 'Grades', 'Marks entry and report cards', 'graduation-cap', 'grades.index', 'amber', 'grades.view'),
            self::tile('transport', 'Transport', 'Buses, drivers, and riders', 'bus', 'transport.index', 'cyan', 'transport.view'),
        ];

        if ($financeRoute !== null) {
            $modules[] = array_merge(
                self::tile('finance', 'Finance', 'Fees, expenses, and payroll', 'dollar-sign', $financeRoute, 'green'),
                ['any_permissions' => ['fees.view', 'expenses.view', 'payroll.view']],
            );
        }

        $modules[] = self::tile('documents', 'Documents', 'ID cards, certificates, receipts', 'file-text', 'documents.index', 'orange', 'documents.view');
        $modules[] = self::tile('notifications', 'Notifications', 'SMS templates and send log', 'bell', 'notifications.index', 'rose', 'notifications.view');
        $modules[] = self::tile('reports', 'Reports', 'Academic, fees, and payroll reports', 'bar-chart', 'reports.index', 'blue', 'reports.view');
        $modules[] = self::tile('settings', 'Settings', 'Users, school profile, and fees', 'settings', 'settings.index', 'slate', 'settings.manage');

        return $modules;
    }

    /**
     * First finance screen the user is allowed to open.
     */
    private static function financeLandingRoute(User $user): ?string
    {
        if ($user->hasPermission('fees.view')) {
            return 'finance.fees-dashboard';
        }
        if ($user->hasPermission('expenses.view')) {
            return 'finance.expenses';
        }
        if ($user->hasPermission('payroll.view')) {
            return 'payroll.index';
        }

        return null;
    }

    /**
     * Insert Check in / Check out after Overview when the user is linked to staff.
     *
     * @param  list<array{key: string, label: string, description: string, icon: string, route: string, tone: string, permission?: string}>  $modules
     * @return list<array{key: string, label: string, description: string, icon: string, route: string, tone: string, permission?: string}>
     */
    private static function withStaffCheckinTab(array $modules, User $user): array
    {
        $staff = $user->staff;
        if (! $staff || blank($staff->checkin_token)) {
            return $modules;
        }

        $action = StaffAttendancePunch::nextAction($staff);
        if ($action === 'done') {
            return $modules;
        }

        $isCheckout = $action === 'check_out';
        $tile = self::tile(
            'staff-checkin',
            $isCheckout ? 'Check out' : 'Check in',
            $isCheckout ? 'End your shift for today' : 'Start your shift for today',
            $isCheckout ? 'log-out' : 'check-circle',
            'staff-checkin.mine',
            $isCheckout ? 'rose' : 'emerald',
        );

        $out = [];
        $inserted = false;
        foreach ($modules as $module) {
            $out[] = $module;
            if ($module['key'] === 'overview') {
                $out[] = $tile;
                $inserted = true;
            }
        }

        if (! $inserted) {
            array_unshift($out, $tile);
        }

        return $out;
    }

    /**
     * @return array{key: string, label: string, description: string, icon: string, route: string, tone: string, permission?: string}
     */
    private static function tile(
        string $key,
        string $label,
        string $description,
        string $icon,
        string $route,
        string $tone,
        ?string $permission = null,
    ): array {
        $tile = compact('key', 'label', 'description', 'icon', 'route', 'tone');
        if ($permission !== null) {
            $tile['permission'] = $permission;
        }

        return $tile;
    }

    /**
     * Tailwind classes for tile icon chip backgrounds.
     */
    public static function toneClasses(string $tone): string
    {
        return match ($tone) {
            'navy' => 'bg-[#1e3a6e]/10 text-[#1e3a6e]',
            'sky' => 'bg-sky-50 text-sky-700',
            'indigo' => 'bg-indigo-50 text-indigo-700',
            'teal' => 'bg-teal-50 text-teal-700',
            'emerald' => 'bg-emerald-50 text-emerald-700',
            'amber' => 'bg-amber-50 text-amber-700',
            'cyan' => 'bg-cyan-50 text-cyan-700',
            'green' => 'bg-green-50 text-green-700',
            'violet' => 'bg-violet-50 text-violet-700',
            'orange' => 'bg-orange-50 text-orange-700',
            'rose' => 'bg-rose-50 text-rose-700',
            'blue' => 'bg-blue-50 text-blue-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }
}
