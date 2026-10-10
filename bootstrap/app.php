<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'vendor.only' => \App\Http\Middleware\EnsureVendorRole::class,
        ]);

        // Stops the browser back/forward cache from showing a stale page
        // after the session's authenticated user changes (staff
        // impersonation switch, logout) — see class docblock.
        $middleware->web(append: [
            \App\Http\Middleware\PreventBackHistoryCaching::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A raw "419 Page Expired" page (stale CSRF token — usually a form
        // left open past the session lifetime) isn't actionable for a
        // vendor/staff user. Send them back to what they were doing instead.
        // Laravel's exception pipeline converts TokenMismatchException into a
        // generic Symfony HttpException(419, ...) in prepareException() before
        // any renderable callback runs, so this has to match on the converted
        // type + status code rather than TokenMismatchException itself.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 419 && ! $request->expectsJson()) {
                return redirect()->back()->with('error', 'Your session expired. Please try that again.');
            }
        });
    })->create();
