<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out anyone whose account has been switched off while they were
 * signed in: a staff member the owner deactivated, or the staff of a
 * pharmacy whose owner deleted the account. Without this they would keep
 * working until their session ran out.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401, 'This account is no longer active.');
            }

            return redirect()->route('login')->with('error', 'This account is no longer active. Contact the pharmacy owner or CuraD support.');
        }

        return $next($request);
    }
}
