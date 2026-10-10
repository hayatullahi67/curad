<?php

namespace App\Http\Controllers;

use App\Services\Maps\PlaceSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Turns a map position into a street address ("Use my location"), and a
 * typed address into a map position, for every address form. Answers are
 * remembered for a day so the same spot is never asked for twice.
 *
 * For a position, the aim is something a rider can find: the street, with
 * the nearest known building as a landmark; and where the map has no street
 * there, the nearest bus stop or landmark instead.
 */
class LocationController extends Controller
{
    private const SERVICE = 'https://nominatim.openstreetmap.org';

    /**
     * Addresses that match what someone has typed so far, for the list that
     * drops down under an address box. Open to visitors, because a customer
     * gives their address while signing up.
     */
    public function suggest(Request $request, PlaceSearch $places)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:200'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ]);

        return response()->json($places->suggest(
            $data['q'],
            isset($data['lat'], $data['lng']) ? (float) $data['lat'] : null,
            isset($data['lat'], $data['lng']) ? (float) $data['lng'] : null,
        ));
    }

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_without:q'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'q' => ['nullable', 'string', 'min:3', 'max:200'],
        ]);

        $place = isset($data['lat'], $data['lng'])
            ? $this->fromPosition((float) $data['lat'], (float) $data['lng'])
            : $this->fromText(trim($data['q']));

        if (! $place) {
            return response()->json(['message' => isset($data['q']) ? 'We could not find that address. Add the area or city, or drop the pin yourself.' : 'No address was found for that spot. Type it in instead.'], 404);
        }

        return response()->json($place);
    }

    private function fromPosition(float $latitude, float $longitude): ?array
    {
        // About 11 metres: close enough to be the same building.
        $key = sprintf('spot2:%.4f,%.4f', $latitude, $longitude);

        return $this->remember($key, function () use ($latitude, $longitude) {
            // Two sources, each good at one thing: the free lookup knows which
            // road a point is on; Geoapify knows the nearest building, and a
            // street when the free lookup has none.
            $here = ($result = $this->ask('/reverse', ['lat' => $latitude, 'lon' => $longitude, 'zoom' => 18])) ? $this->parts($result) : [];
            $near = $this->nearestBuilding($latitude, $longitude) ?? [];

            if (! $here && ! $near) {
                return null;
            }

            $street = $here['street'] ?? $near['street'] ?? null;
            $landmark = $near['name'] ?? null;

            // A house number is only claimed when the spot is practically at that door, on that street.
            $number = $here['number'] ?? (($near['metres'] ?? 999) <= 30 && ($near['street'] ?? null) === $street ? ($near['number'] ?? null) : null);

            // A building counts as a landmark when it is close enough to see.
            if ($landmark && (($near['metres'] ?? 999) > 150 || $landmark === $street)) {
                $landmark = null;
            }

            // Nothing close by on the map: the nearest bus stop or named place, even if further off.
            if (! $street && ! $landmark) {
                $landmark = $this->nearestLandmark($latitude, $longitude);
            }

            $city = $here['city'] ?? $near['city'] ?? null;
            $area = $here['area'] ?? $near['area'] ?? null;

            $address = collect([
                $landmark && ! $number ? 'Near ' . $landmark : null,
                $street ? trim(($number ?? '') . ' ' . $street) : null,
                $area !== $city ? $area : null,
            ])->filter()->unique()->implode(', ');

            if ($address === '') {
                return null;
            }

            return [
                'address' => $address,
                'city' => $city,
                'state' => $here['state'] ?? $near['state'] ?? null,
                'label' => $here['label'] ?? $near['label'] ?? null,
                'latitude' => round($latitude, 6),
                'longitude' => round($longitude, 6),
            ];
        });
    }

    /** The nearest known building to a position, from Geoapify: its name, street, house number and how far it is. */
    private function nearestBuilding(float $latitude, float $longitude): ?array
    {
        $found = $this->geoapify('https://api.geoapify.com/v1/geocode/reverse', ['lat' => $latitude, 'lon' => $longitude, 'format' => 'json', 'lang' => 'en'])['results'][0] ?? null;

        if (! $found) {
            return null;
        }

        $street = $found['street'] ?? null;
        $name = $found['name'] ?? null;

        return array_filter([
            'street' => $street,
            // A street is sometimes returned as its own "name"; that is not a landmark.
            'name' => $name !== $street ? $name : null,
            'number' => $found['housenumber'] ?? null,
            'metres' => (float) ($found['distance'] ?? 0),
            'city' => $found['city'] ?? $found['town'] ?? $found['village'] ?? null,
            'area' => $found['suburb'] ?? $found['district'] ?? $found['county'] ?? null,
            'state' => isset($found['state']) ? preg_replace('/\s+State$/i', '', $found['state']) : null,
            'label' => $found['formatted'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /** The closest bus stop within 2 km, or failing that the closest named place within 1 km, with how far it is. */
    private function nearestLandmark(float $latitude, float $longitude): ?string
    {
        foreach (['public_transport' => [2000, ' bus stop'], 'commercial,catering,education,healthcare,religion,service,office,accommodation,leisure' => [1000, '']] as $categories => [$metres, $suffix]) {
            $places = $this->geoapify('https://api.geoapify.com/v2/places', [
                'categories' => $categories,
                'filter' => "circle:{$longitude},{$latitude},{$metres}",
                'bias' => "proximity:{$longitude},{$latitude}",
                'limit' => 5,
                'lang' => 'en',
            ])['features'] ?? [];

            foreach ($places as $place) {
                if (filled($name = $place['properties']['name'] ?? null)) {
                    // "Ojota Bus Stop bus stop" would be silly.
                    return $name . (stripos($name, 'stop') !== false || stripos($name, 'park') !== false ? '' : $suffix);
                }
            }
        }

        return null;
    }

    /** One question to Geoapify, counted against its daily allowance. Null when it may not or cannot be asked. */
    private function geoapify(string $url, array $query): ?array
    {
        $places = app(PlaceSearch::class);

        if (! $places->available('geoapify')) {
            return null;
        }

        try {
            $places->count('geoapify');

            $response = Http::timeout(8)
                ->retry(2, 300, throw: false)
                ->withOptions(['verify' => config('curad.ca_bundle') ?: true])
                ->get($url, $query + ['apiKey' => config('curad.places.geoapify.key')]);

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $problem) {
            report($problem);

            return null;
        }
    }

    private function fromText(string $text): ?array
    {
        return $this->remember('place:for:' . md5(mb_strtolower($text)), function () use ($text) {
            $results = $this->ask('/search', ['q' => $text, 'limit' => 1, 'countrycodes' => 'ng']);
            $first = is_array($results) ? ($results[0] ?? null) : null;

            return $first ? $this->shape($first, (float) $first['lat'], (float) $first['lon']) : null;
        });
    }

    /** Keeps a found place for a day. A failed lookup is not kept, so it is tried again next time. */
    private function remember(string $key, callable $find): ?array
    {
        $place = Cache::get($key) ?? $find();

        if ($place) {
            Cache::put($key, $place, now()->addDay());
        }

        return $place;
    }

    private function ask(string $path, array $query): ?array
    {
        try {
            $response = Http::timeout(8)
                // Some Windows installs of PHP ship without the list of trusted
                // certificate authorities; CURAD_CA_BUNDLE points at one.
                ->withOptions(['verify' => config('curad.ca_bundle') ?: true])
                ->withHeaders(['User-Agent' => 'CuraD/1.0 (' . config('app.url') . ')', 'Accept-Language' => 'en'])
                ->get(self::SERVICE . $path, $query + ['format' => 'jsonv2', 'addressdetails' => 1]);

            return $response->successful() && ! isset($response['error']) ? $response->json() : null;
        } catch (\Throwable $problem) {
            report($problem);

            return null;
        }
    }

    /** What the free lookup knows about a spot: the road it is on, a house number, the area, city and state. */
    private function parts(array $result): array
    {
        $parts = $result['address'] ?? [];
        $city = $parts['city'] ?? $parts['town'] ?? $parts['municipality'] ?? $parts['county'] ?? $parts['village'] ?? null;

        return array_filter([
            'street' => $parts['road'] ?? $parts['pedestrian'] ?? null,
            'number' => $parts['house_number'] ?? null,
            'area' => $parts['suburb'] ?? $parts['neighbourhood'] ?? $parts['quarter'] ?? $parts['village'] ?? null,
            'city' => $city,
            'state' => isset($parts['state']) ? preg_replace('/\s+State$/i', '', $parts['state']) : null,
            'label' => $result['display_name'] ?? null,
        ], fn ($value) => filled($value));
    }

    /** The parts of the answer the forms use: street line, city or area, state. */
    private function shape(array $result, float $latitude, float $longitude): array
    {
        $parts = $result['address'] ?? [];
        $street = trim(($parts['house_number'] ?? '') . ' ' . ($parts['road'] ?? $parts['pedestrian'] ?? ''));
        $area = $parts['suburb'] ?? $parts['neighbourhood'] ?? $parts['quarter'] ?? $parts['village'] ?? null;
        $city = $parts['city'] ?? $parts['town'] ?? $parts['municipality'] ?? $parts['county'] ?? $parts['village'] ?? null;

        return [
            'address' => collect([$street ?: ($result['name'] ?? null), $area !== $city ? $area : null])->filter()->unique()->implode(', '),
            'city' => $city,
            'state' => isset($parts['state']) ? preg_replace('/\s+State$/i', '', $parts['state']) : null,
            'label' => $result['display_name'] ?? null,
            'latitude' => round($latitude, 6),
            'longitude' => round($longitude, 6),
        ];
    }
}
