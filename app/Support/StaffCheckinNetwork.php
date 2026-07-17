<?php

namespace App\Support;

use App\Models\SchoolSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class StaffCheckinNetwork
{
    /**
     * @return list<string>
     */
    public static function allowedCidrs(): array
    {
        $raw = trim((string) (SchoolSetting::get('staff_attendance_allowed_cidrs', '') ?? ''));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $part) => trim($part),
            preg_split('/[\s,;]+/', $raw) ?: []
        )));
    }

    public static function isAllowed(?string $ip): bool
    {
        $cidrs = self::allowedCidrs();

        // Fail open only in local/testing when no CIDRs configured (dev convenience).
        if ($cidrs === []) {
            return App::environment(['local', 'testing']);
        }

        if ($ip === null || $ip === '') {
            return false;
        }

        foreach ($cidrs as $cidr) {
            if (self::ipMatches($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    public static function assertAllowed(Request $request): void
    {
        if (! self::isAllowed($request->ip())) {
            abort(403, 'Staff check-in only works on school Wi‑Fi. Connect to the school network and try again.');
        }
    }

    public static function ipMatches(string $ip, string $cidr): bool
    {
        $cidr = trim($cidr);
        if ($cidr === '') {
            return false;
        }

        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $mask] = explode('/', $cidr, 2);
        $mask = (int) $mask;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if ($mask < 0 || $mask > 32) {
                return false;
            }
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            if ($ipLong === false || $subnetLong === false) {
                return false;
            }
            $maskLong = $mask === 0 ? 0 : (~((1 << (32 - $mask)) - 1) & 0xFFFFFFFF);

            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        // Basic IPv6 exact / prefix support
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($mask < 0 || $mask > 128) {
                return false;
            }
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);
            if ($ipBin === false || $subnetBin === false) {
                return false;
            }
            $bytes = intdiv($mask, 8);
            $bits = $mask % 8;
            if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
                return false;
            }
            if ($bits === 0) {
                return true;
            }
            $maskByte = (~((1 << (8 - $bits)) - 1)) & 0xFF;

            return (ord($ipBin[$bytes]) & $maskByte) === (ord($subnetBin[$bytes]) & $maskByte);
        }

        return false;
    }
}
