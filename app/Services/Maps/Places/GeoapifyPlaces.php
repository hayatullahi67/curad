<?php

namespace App\Services\Maps\Places;

use Illuminate\Http\Client\PendingRequest;

/** Address suggestions from Geoapify (OpenStreetMap data). Free plan: 3,000 requests a day. */
class GeoapifyPlaces implements PlaceProvider
{
    public function __construct(private PendingRequest $http, private array $settings)
    {
    }

    public function search(string $text, ?float $latitude, ?float $longitude, int $limit): array
    {
        $response = $this->http->get('https://api.geoapify.com/v1/geocode/autocomplete', array_filter([
            'text' => $text,
            'filter' => 'countrycode:' . $this->settings['country'],
            'bias' => $latitude !== null && $longitude !== null ? "proximity:{$longitude},{$latitude}" : null,
            'limit' => $limit,
            'lang' => 'en',
            'format' => 'json',
            'apiKey' => $this->settings['key'],
        ], fn ($value) => $value !== null));

        if ($response->status() === 429) {
            throw new PlaceLimitReached();
        }

        $response->throw();

        return collect($response->json('results') ?? [])->map(function (array $place) {
            $title = $place['address_line1'] ?? $place['name'] ?? $place['street'] ?? '';
            $city = $place['city'] ?? $place['town'] ?? $place['county'] ?? null;
            // Built from the parts, because the service's own line carries post codes and state codes.
            $rest = array_filter(array_unique([$place['suburb'] ?? null, $city, $place['state'] ?? null]), fn ($part) => filled($part) && $part !== $title);

            return [
                'title' => $title,
                'subtitle' => implode(', ', $rest),
                'label' => implode(', ', [$title, ...$rest]),
                'address' => trim(implode(', ', array_filter([$title, $place['suburb'] ?? null], fn ($part) => filled($part) && $part !== $city))),
                'city' => $city,
                'state' => $place['state'] ?? null,
                'latitude' => $place['lat'] ?? null,
                'longitude' => $place['lon'] ?? null,
            ];
        })->all();
    }
}
