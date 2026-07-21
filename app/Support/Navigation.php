<?php

namespace App\Support;

use App\Models\User;

class Navigation
{
    public const SESSION_KEY = 'current_app';

    /**
     * Sidebar items for the active app only (plus Apps home).
     *
     * @return list<array<string, mixed>>
     */
    public static function for(User $user): array
    {
        $active = self::resolveActiveModule();
        $items = self::filterByPermission(self::allItems(), $user);

        if ($active === null) {
            return $items;
        }

        return self::scopeToModule($items, $active);
    }

    /**
     * Remember / clear the active app from the request and session.
     */
    public static function resolveActiveModule(): ?string
    {
        if (request()->routeIs('modules.home')) {
            session()->forget(self::SESSION_KEY);

            return null;
        }

        $fromQuery = request()->query('app');
        if (is_string($fromQuery) && preg_match('/^[a-z][a-z0-9_-]*$/', $fromQuery) === 1) {
            session([self::SESSION_KEY => $fromQuery]);

            return $fromQuery;
        }

        $inferred = self::inferModuleFromRoute(request()->route()?->getName());
        $shared = request()->routeIs('students.show', 'students.by-parent');

        if ($inferred !== null && ! $shared) {
            session([self::SESSION_KEY => $inferred]);

            return $inferred;
        }

        $session = session(self::SESSION_KEY);

        return is_string($session) && $session !== '' ? $session : $inferred;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function allItems(): array
    {
        return [
            ['type' => 'item', 'label' => 'Back to Apps', 'icon' => 'arrow-left', 'route' => 'modules.home'],
            ['type' => 'item', 'label' => 'Overview', 'icon' => 'home', 'route' => 'dashboard', 'permission' => 'overview.view', 'module' => 'overview'],
            ['type' => 'item', 'label' => 'Classes', 'icon' => 'layers', 'route' => 'classes.index', 'permission' => 'classes.view', 'module' => 'classes'],
            ['type' => 'item', 'label' => 'Staff', 'icon' => 'briefcase', 'route' => 'staff.index', 'permission' => 'staff.view', 'module' => 'staff'],
            [
                'type' => 'group',
                'label' => 'Timetable',
                'icon' => 'calendar',
                'module' => 'timetable',
                'children' => [
                    ['label' => 'Schedule', 'route' => 'timetable.index', 'permission' => 'timetable.view', 'module' => 'timetable'],
                    ['label' => 'Requirements', 'route' => 'timetable.requirements', 'permission' => 'timetable.view', 'module' => 'timetable'],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Attendance',
                'icon' => 'check-circle',
                'module' => 'attendance',
                'children' => [
                    ['label' => 'Students', 'route' => 'attendance.index', 'permission' => 'attendance.view', 'module' => 'attendance'],
                    ['label' => 'Staff', 'route' => 'staff-attendance.index', 'permission' => 'staff.manage', 'module' => 'attendance'],
                    ['label' => 'Staff history', 'route' => 'staff-attendance.history', 'permission' => 'staff.view', 'module' => 'attendance'],
                ],
            ],
            ['type' => 'item', 'label' => 'Grades', 'icon' => 'graduation-cap', 'route' => 'grades.index', 'permission' => 'grades.view', 'module' => 'grades'],
            ['type' => 'item', 'label' => 'Transport', 'icon' => 'bus', 'route' => 'transport.index', 'permission' => 'transport.view', 'module' => 'transport'],
            [
                'type' => 'group',
                'label' => 'Finance',
                'icon' => 'dollar-sign',
                'module' => 'finance',
                'children' => [
                    ['label' => 'Fees Dashboard', 'route' => 'finance.fees-dashboard', 'permission' => 'fees.view', 'module' => 'finance'],
                    ['label' => 'Fee Collection', 'route' => 'finance.fee-collection', 'permission' => 'fees.view', 'module' => 'finance'],
                    ['label' => 'Expenses', 'route' => 'finance.expenses', 'permission' => 'expenses.view', 'module' => 'finance'],
                    ['label' => 'Payroll', 'route' => 'payroll.index', 'permission' => 'payroll.view', 'module' => 'finance'],
                ],
            ],
            ['type' => 'item', 'label' => 'Documents', 'icon' => 'file-text', 'route' => 'documents.index', 'permission' => 'documents.view', 'module' => 'documents'],
            ['type' => 'item', 'label' => 'Notifications', 'icon' => 'bell', 'route' => 'notifications.index', 'permission' => 'notifications.view', 'module' => 'notifications'],
            ['type' => 'item', 'label' => 'Reports', 'icon' => 'bar-chart', 'route' => 'reports.index', 'permission' => 'reports.view', 'module' => 'reports'],
            ['type' => 'item', 'label' => 'Settings', 'icon' => 'settings', 'route' => 'settings.index', 'permission' => 'settings.manage', 'module' => 'settings'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function filterByPermission(array $items, User $user): array
    {
        $out = [];

        foreach ($items as $item) {
            if (($item['type'] ?? 'item') === 'group') {
                $children = [];
                foreach ($item['children'] as $child) {
                    $perm = $child['permission'] ?? null;
                    if ($perm === null || $user->hasPermission($perm)) {
                        $children[] = $child;
                    }
                }
                if ($children === []) {
                    continue;
                }
                $item['children'] = $children;
                $out[] = $item;

                continue;
            }

            $perm = $item['permission'] ?? null;
            if ($perm === null || $user->hasPermission($perm)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Keep Apps + items/groups that belong to the active module.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function scopeToModule(array $items, string $module): array
    {
        $out = [];

        foreach ($items as $item) {
            if (($item['route'] ?? null) === 'modules.home') {
                $out[] = $item;

                continue;
            }

            if (($item['type'] ?? 'item') === 'group') {
                $children = array_values(array_filter(
                    $item['children'],
                    fn (array $child) => ($child['module'] ?? $item['module'] ?? null) === $module
                ));
                if ($children === []) {
                    continue;
                }
                $item['children'] = $children;
                if (($item['module'] ?? null) === null || ($item['module'] ?? null) === $module) {
                    $out[] = $item;
                }

                continue;
            }

            if (($item['module'] ?? null) === $module) {
                $out[] = $item;
            }
        }

        return $out;
    }

    private static function inferModuleFromRoute(?string $routeName): ?string
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        return match (true) {
            $routeName === 'dashboard' => 'overview',
            str_starts_with($routeName, 'classes.') => 'classes',
            str_starts_with($routeName, 'students.') => 'classes',
            str_starts_with($routeName, 'staff-attendance.') => 'attendance',
            str_starts_with($routeName, 'staff.') => 'staff',
            str_starts_with($routeName, 'timetable.') => 'timetable',
            str_starts_with($routeName, 'attendance.') => 'attendance',
            str_starts_with($routeName, 'grades.') => 'grades',
            str_starts_with($routeName, 'transport.') => 'transport',
            in_array($routeName, ['finance.fees-dashboard', 'finance.fee-collection', 'finance.payments.receipt', 'finance.fee-collection.generate', 'finance.payments.store', 'finance.accounting'], true) => 'finance',
            str_starts_with($routeName, 'finance.expense') => 'finance',
            str_starts_with($routeName, 'payroll.') => 'finance',
            str_starts_with($routeName, 'documents.') => 'documents',
            str_starts_with($routeName, 'notifications.') => 'notifications',
            str_starts_with($routeName, 'reports.') => 'reports',
            str_starts_with($routeName, 'settings.') => 'settings',
            default => null,
        };
    }
}
