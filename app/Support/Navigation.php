<?php

namespace App\Support;

use App\Enums\UserRole;

class Navigation
{
    /**
     * Role-based sidebar items matching /design-reference.
     *
     * @return list<array<string, mixed>>
     */
    public static function for(UserRole $role): array
    {
        return match ($role) {
            UserRole::SuperAdmin, UserRole::Admin => self::adminNav(),
            UserRole::Finance => self::financeNav(),
            UserRole::Teacher => self::teacherNav(),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function adminNav(): array
    {
        return [
            ['type' => 'item', 'label' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard'],
            ['type' => 'item', 'label' => 'Classes', 'icon' => 'layers', 'route' => 'classes.index'],
            ['type' => 'item', 'label' => 'Staff', 'icon' => 'briefcase', 'route' => 'staff.index'],
            ['type' => 'item', 'label' => 'Timetable', 'icon' => 'calendar', 'route' => 'timetable.index'],
            [
                'type' => 'group',
                'label' => 'Attendance',
                'icon' => 'check-circle',
                'children' => [
                    ['label' => 'Students', 'route' => 'attendance.index'],
                    ['label' => 'Staff', 'route' => 'staff-attendance.index'],
                ],
            ],
            ['type' => 'item', 'label' => 'Grades', 'icon' => 'graduation-cap', 'route' => 'grades.index'],
            ['type' => 'item', 'label' => 'Transport', 'icon' => 'bus', 'route' => 'transport.index'],
            [
                'type' => 'group',
                'label' => 'Finance',
                'icon' => 'dollar-sign',
                'children' => [
                    ['label' => 'Fees Dashboard', 'route' => 'finance.fees-dashboard'],
                    ['label' => 'Fee Collection', 'route' => 'finance.fee-collection'],
                ],
            ],
            ['type' => 'item', 'label' => 'Payroll', 'icon' => 'credit-card', 'route' => 'payroll.index'],
            ['type' => 'item', 'label' => 'Documents', 'icon' => 'file-text', 'route' => 'documents.index'],
            ['type' => 'item', 'label' => 'Notifications', 'icon' => 'bell', 'route' => 'notifications.index'],
            ['type' => 'item', 'label' => 'Reports', 'icon' => 'bar-chart', 'route' => 'reports.index'],
            ['type' => 'item', 'label' => 'Settings', 'icon' => 'settings', 'route' => 'settings.index'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function financeNav(): array
    {
        return [
            ['type' => 'item', 'label' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard'],
            ['type' => 'item', 'label' => 'Transport', 'icon' => 'bus', 'route' => 'transport.index'],
            [
                'type' => 'group',
                'label' => 'Finance',
                'icon' => 'dollar-sign',
                'children' => [
                    ['label' => 'Fees Dashboard', 'route' => 'finance.fees-dashboard'],
                    ['label' => 'Fee Collection', 'route' => 'finance.fee-collection'],
                ],
            ],
            ['type' => 'item', 'label' => 'Payroll', 'icon' => 'credit-card', 'route' => 'payroll.index'],
            ['type' => 'item', 'label' => 'Documents', 'icon' => 'file-text', 'route' => 'documents.index'],
            ['type' => 'item', 'label' => 'Reports', 'icon' => 'bar-chart', 'route' => 'reports.index'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function teacherNav(): array
    {
        return [
            ['type' => 'item', 'label' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard'],
            ['type' => 'item', 'label' => 'Classes', 'icon' => 'layers', 'route' => 'classes.index'],
            ['type' => 'item', 'label' => 'Timetable', 'icon' => 'calendar', 'route' => 'timetable.index'],
            ['type' => 'item', 'label' => 'Attendance', 'icon' => 'check-circle', 'route' => 'attendance.index'],
            ['type' => 'item', 'label' => 'Grades', 'icon' => 'graduation-cap', 'route' => 'grades.index'],
        ];
    }
}
