<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Writes the "who did what, when" trail shown on the Activity Log page.
 *
 * Action names are `group.event` (sale.voided, stock.adjusted, ...) — the
 * group is what the page filters on, see AuditLog::GROUPS.
 */
class Audit
{
    private static bool $quiet = false;

    public static function log(string $action, string $description, ?int $pharmacyId = null, ?Model $subject = null, array $meta = [], ?int $userId = null): void
    {
        // The trail must never be the reason a sale or stock change fails
        // (e.g. the table is missing on a host where migrations lag behind).
        try {
            AuditLog::create([
                'pharmacy_id' => $pharmacyId,
                'user_id' => $userId ?? Auth::id(),
                'action' => $action,
                'description' => Str::limit($description, 250, '…'),
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'meta' => $meta ?: null,
                'ip_address' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * An account event (sign-in, sign-out, new account, password change).
     * It is filed under the pharmacy the person owns or works for, with the
     * device they used, so the owner can spot a sign-in that was not theirs.
     */
    public static function account(string $action, string $description, ?User $user, array $meta = []): void
    {
        $pharmacyId = null;

        try {
            $pharmacyId = $user ? Pharmacy::resolveForUser($user)?->id : null;
        } catch (\Throwable $e) {
            report($e);
        }

        static::log($action, $description, $pharmacyId, null, $meta + ['device' => static::device()], $user?->id);
    }

    /**
     * Runs something without recording sign-ins or sign-outs. Used when the
     * owner steps into a staff account: that is logged as "viewed as staff",
     * not as the staff member signing in.
     */
    public static function quietly(callable $action): mixed
    {
        static::$quiet = true;

        try {
            return $action();
        } finally {
            static::$quiet = false;
        }
    }

    public static function isQuiet(): bool
    {
        return static::$quiet;
    }

    /** The browser and device in plain words, e.g. "Chrome on Windows". */
    public static function device(): string
    {
        $agent = (string) request()?->userAgent();

        if ($agent === '') {
            return 'Unknown device';
        }

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/'), str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $system = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'unknown device',
        };

        return "{$browser} on {$system}";
    }
}
