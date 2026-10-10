<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Events\Dispatcher;

/**
 * Puts account events on the Activity Log: sign-ins, sign-outs, wrong
 * passwords, new accounts and password resets. It listens to Laravel's own
 * sign-in events, so every way of signing in (main page, staff page,
 * "keep me signed in") is covered without each one having to remember.
 */
class RecordAccountActivity
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'signedIn',
            Logout::class => 'signedOut',
            Failed::class => 'failed',
            Registered::class => 'registered',
            PasswordReset::class => 'passwordReset',
        ];
    }

    public function signedIn(Login $event): void
    {
        $user = $event->user;

        // A deactivated account is turned away straight after the password
        // check; the controller records that as a blocked sign-in instead.
        if (Audit::isQuiet() || ! $user instanceof User || ! $user->is_active) {
            return;
        }

        Audit::account('account.signed_in', $this->who($user) . ' signed in' . ($event->remember ? ' (kept signed in on this device)' : ''), $user);
    }

    public function signedOut(Logout $event): void
    {
        $user = $event->user;

        if (Audit::isQuiet() || ! $user instanceof User || ! $user->is_active) {
            return;
        }

        Audit::account('account.signed_out', $this->who($user) . ' signed out', $user);
    }

    public function failed(Failed $event): void
    {
        $email = (string) ($event->credentials['email'] ?? '');
        // Laravel passes the account when the email exists and only the
        // password was wrong; with an unknown email there is no account.
        $user = $event->user instanceof User ? $event->user : null;

        if ($user) {
            Audit::account('account.sign_in_failed', 'Wrong password entered for ' . $this->who($user), $user);
        } elseif ($email !== '') {
            Audit::account('account.sign_in_failed', "Sign-in attempt with an unknown email ({$email})", null);
        }
    }

    public function registered(Registered $event): void
    {
        $user = $event->user;

        if ($user instanceof User) {
            Audit::account('account.created', $this->who($user) . ' created ' . ($user->role === 'vendor' ? 'a pharmacy owner account' : 'a customer account'), $user);
        }
    }

    public function passwordReset(PasswordReset $event): void
    {
        $user = $event->user;

        if ($user instanceof User) {
            Audit::account('account.password_reset', $this->who($user) . ' set a new password using a reset link', $user);
        }
    }

    private function who(User $user): string
    {
        return $user->full_name ?: $user->email;
    }
}
