<?php

namespace App\Services\Maps\Places;

/**
 * One address service that can suggest places for a few typed letters.
 * To add another (Google, say): write a class like the ones beside this
 * file, list it in PlaceSearch::PROVIDERS, and put its name first in
 * config/curad.php, "places.order".
 */
interface PlaceProvider
{
    /**
     * Places matching the typed text, nearest to the given point first when
     * one is given. Each place is an array with: title, subtitle, label,
     * address, city, state, latitude, longitude.
     *
     * @throws PlaceLimitReached  when the service says today's allowance is used up
     * @throws \Throwable         when the service cannot be reached or answers badly
     */
    public function search(string $text, ?float $latitude, ?float $longitude, int $limit): array;
}
