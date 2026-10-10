<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sign-ins, sign-outs and new accounts go on the Activity Log.
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\RecordAccountActivity::class);

        // Batches expire as days pass; products holding one are refreshed daily.
        try {
            if (! $this->app->runningInConsole()) {
                \App\Services\Inventory\StockLedger::refreshExpired();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
