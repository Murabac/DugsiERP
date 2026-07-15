<?php

namespace App\Support;

use App\Models\Grade;
use App\Models\SchoolSetting;
use App\Models\User;
use Carbon\CarbonInterface;

class GradeEditRules
{
    public static function windowDays(): int
    {
        return SchoolSetting::gradeEditWindowDays();
    }

    /**
     * Clock used for lock / note windows (never null for persisted grades).
     */
    public static function enteredAt(Grade $grade): CarbonInterface
    {
        return $grade->first_entered_at ?? $grade->created_at ?? now();
    }

    /**
     * Teachers (incl. headmasters) may still edit until this moment.
     * Admins always bypass.
     */
    public static function unlockUntil(Grade $grade): CarbonInterface
    {
        return self::enteredAt($grade)->copy()->addDays(self::windowDays());
    }

    public static function isLockedFor(User $user, ?Grade $grade): bool
    {
        if ($user->isAdmin()) {
            return false;
        }

        if ($grade === null) {
            return false;
        }

        // Missing clock is treated as locked for teachers (fail closed).
        if ($grade->first_entered_at === null && $grade->created_at === null) {
            return true;
        }

        return now()->gte(self::unlockUntil($grade));
    }

    /**
     * When changing an existing grade (score and/or remarks):
     * - Admins / Super Admins must always provide a why note.
     * - Teachers must provide a note after the first 24 hours (while still unlocked).
     */
    public static function requiresEditNote(User $user, ?Grade $grade): bool
    {
        if ($grade === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (self::isLockedFor($user, $grade)) {
            return false;
        }

        return now()->gte(self::enteredAt($grade)->copy()->addDay());
    }
}
