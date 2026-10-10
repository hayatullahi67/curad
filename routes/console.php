<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('curad:places-usage', function (\App\Services\Maps\PlaceSearch $places) {
    $this->table(['Service', 'Asked today', 'Daily limit', 'In use now'], collect($places->usageToday())->map(fn ($usage, $name) => [
        $name, $usage['asked'], $usage['limit'] ?: 'none', $usage['available'] ? 'yes' : 'no (resting, used up or no key)',
    ])->values()->all());
})->purpose('Show how many address suggestions each service has answered today');
