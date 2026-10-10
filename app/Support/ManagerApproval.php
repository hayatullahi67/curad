<?php

namespace App\Support;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Supervisor sign-off for the things a cashier may not do alone: voiding a
 * sale, refunding one, or giving a discount above their limit.
 */
class ManagerApproval
{
    /**
     * The owner, and staff roles marked can_approve_voids, approve for
     * themselves and for others.
     */
    public static function isSupervisor(User $user): bool
    {
        return $user->role === 'vendor' || StaffRoles::canApproveVoids($user->staff_role);
    }

    /**
     * Who is signing this action off. A supervisor signs for themselves.
     * Anyone else must have a supervisor of the same facility type their own
     * email and password into the form — the till never changes hands and
     * the supervisor is not logged in, only checked.
     *
     * @throws ValidationException when approval is missing or wrong
     */
    public static function resolve(Request $request, User $user, Pharmacy $pharmacy): User
    {
        if (self::isSupervisor($user)) {
            return $user;
        }

        $request->validate([
            'manager_email' => 'required|email',
            'manager_password' => 'required|string',
        ], [
            'manager_email.required' => 'A manager must enter their email to approve this.',
            'manager_password.required' => 'A manager must enter their password to approve this.',
        ]);

        $throttleKey = 'pos-approval:' . $user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'manager_password' => 'Too many failed approvals. Wait ' . RateLimiter::availableIn($throttleKey) . ' seconds and try again.',
            ]);
        }

        $manager = User::where('email', $request->manager_email)->first();

        $belongsHere = $manager && (
            ((int) $manager->id === (int) $pharmacy->user_id)
            || ($manager->role === 'sales_person' && Pharmacy::resolveForUser($manager)?->id === $pharmacy->id)
        );

        $approved = $belongsHere
            && self::isSupervisor($manager)
            && ($manager->is_active ?? true)
            && $manager->password_hash
            && password_verify($request->manager_password, $manager->password_hash);

        if (! $approved) {
            RateLimiter::hit($throttleKey, 60);

            // One message for every failure, so the form cannot be used to
            // find out which emails are managers.
            throw ValidationException::withMessages([
                'manager_password' => 'Manager approval failed. Check the email and password, and make sure the account is the owner or a supervisor at this facility.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        return $manager;
    }
}
