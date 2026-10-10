<?php

namespace App\Services\Maps\Places;

use Illuminate\Http\Client\PendingRequest;

/** Address suggestions from LocationIQ (OpenStreetMap data). Free plan: 5,000 requests a day, 2 a second. */
class LocationIqPlaces implements PlaceProvider
{
    public function __construct(private PendingRequest $http, private array $settings)
    {
    }

    public function search(string $text, ?float $latitude, ?float $longitude, int $limit): array
    {
        $query = [
            'key' => $this->settings['key'],
            'q' => $text,
            'countrycodes' => $this->settings['country'],
            'limit' => $limit,
            'dedupe' => 1,
            'normalizecity' => 1,
            'accept-language' => 'en',
        ];

        // Prefer places around the shopper: a box of roughly 50 km each way.
        if ($latitude !== null && $longitude !== null) {
            $query['viewbox'] = implode(',', [$longitude - 0.5, $latitude + 0.5, $longitude + 0.5, $latitude - 0.5]);
        }

        $response = $this->http->get('https://api.locationiq.com/v1/autocomplete', $query);

        if ($response->status() === 429) {
            // "Rate Limited Day" means today's allowance; "Second" or "Minute" only means slow down.
            throw new PlaceLimitReached(str_contains(strtolower((string) $response->json('error')), 'day'));
        }

        // This service answers "not found" when nothing matches; that is an empty list, not a failure.
        if ($response->status() === 404) {
            return [];
        }

        $response->throw();

        return collect($response->json() ?? [])->filter(fn ($place) => is_array($place) && isset($place['lat'], $place['lon']))->map(function (array $place) {
            $parts = $place['address'] ?? [];
            $street = trim(($parts['house_number'] ?? '') . ' ' . ($parts['road'] ?? ''));
            $title = $place['display_place'] ?? $parts['name'] ?? $street;
            $city = $parts['city'] ?? $parts['town'] ?? $parts['county'] ?? null;
            // Built from the parts, because the service's own line carries post codes.
            $rest = array_filter(array_unique([$street !== $title ? $street : null, $parts['suburb'] ?? null, $city, $parts['state'] ?? null]), fn ($part) => filled($part) && $part !== $title);

            return [
                'title' => $title,
                'subtitle' => implode(', ', $rest),
                'label' => implode(', ', [$title, ...$rest]),
                'address' => trim(implode(', ', array_filter(array_unique([$title, $street, $parts['suburb'] ?? null]), fn ($part) => filled($part) && $part !== $city))),
                'city' => $city,
                'state' => $parts['state'] ?? null,
                'latitude' => (float) $place['lat'],
                'longitude' => (float) $place['lon'],
            ];
        })->values()->all();
    }
}
