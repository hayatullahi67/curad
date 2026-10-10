<?php

namespace App\Services\Maps\Places;

use Illuminate\Http\Client\PendingRequest;

/**
 * Address suggestions from Photon (OpenStreetMap data). No key and no stated
 * daily limit, but the public server is shared and comes with no promise;
 * point "places.photon.url" at your own copy for a live site.
 */
class PhotonPlaces implements PlaceProvider
{
    /** Roughly Nigeria: west, south, east, north. Photon has no country filter, so results are boxed in. */
    private const NIGERIA = '2.6,4.2,14.7,13.9';

    public function __construct(private PendingRequest $http, private array $settings)
    {
    }

    public function search(string $text, ?float $latitude, ?float $longitude, int $limit): array
    {
        $response = $this->http->get(rtrim($this->settings['url'], '/') . '/api/', array_filter([
            'q' => $text,
            'limit' => $limit + 4,   // some are dropped below
            'lang' => 'en',
            'lat' => $latitude,
            'lon' => $longitude,
            'bbox' => $this->settings['country'] === 'ng' ? self::NIGERIA : null,
        ], fn ($value) => $value !== null));

        if ($response->status() === 429) {
            throw new PlaceLimitReached(false);
        }

        $response->throw();

        return collect($response->json('features') ?? [])
            ->filter(fn (array $feature) => strtolower($feature['properties']['countrycode'] ?? '') === $this->settings['country'] && isset($feature['geometry']['coordinates'][1]))
            ->map(function (array $feature) {
                $place = $feature['properties'];
                $street = trim(($place['housenumber'] ?? '') . ' ' . ($place['street'] ?? ''));
                $title = $place['name'] ?? $street;
                $city = $place['city'] ?? $place['county'] ?? null;
                $rest = array_filter(array_unique([$street !== $title ? $street : null, $place['district'] ?? null, $city, $place['state'] ?? null]));

                return [
                    'title' => $title,
                    'subtitle' => implode(', ', $rest),
                    'label' => implode(', ', array_filter([$title, ...$rest])),
                    'address' => trim(implode(', ', array_filter(array_unique([$title, $street, $place['district'] ?? null]), fn ($part) => filled($part) && $part !== $city))),
                    'city' => $city,
                    'state' => $place['state'] ?? null,
                    'latitude' => (float) $feature['geometry']['coordinates'][1],
                    'longitude' => (float) $feature['geometry']['coordinates'][0],
                ];
            })
            ->filter(fn (array $place) => $place['title'] !== '')
            ->take($limit)->values()->all();
    }
}
