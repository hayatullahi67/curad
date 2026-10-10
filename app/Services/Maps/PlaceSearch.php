<?php

namespace App\Services\Maps;

use App\Services\Maps\Places\GeoapifyPlaces;
use App\Services\Maps\Places\LocationIqPlaces;
use App\Services\Maps\Places\PhotonPlaces;
use App\Services\Maps\Places\PlaceLimitReached;
use App\Services\Maps\Places\PlaceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Address suggestions while someone types ("12 all…" → "12 Allen Avenue,
 * Ikeja").
 *
 * Several address services stand in a queue (config/curad.php, "places").
 * The first one answers. When it has used up its allowance for the day, or
 * cannot be reached, the next one answers instead, and so on down the
 * queue. Whoever is typing never sees which service it was.
 *
 * Every answer is remembered for a day, so the same letters typed again
 * cost nothing.
 */
class PlaceSearch
{
    /** Name in the settings => the class that talks to that service. */
    private const PROVIDERS = [
        'geoapify' => GeoapifyPlaces::class,
        'locationiq' => LocationIqPlaces::class,
        'photon' => PhotonPlaces::class,
    ];

    /**
     * @return array{places: array<int, array>, provider: ?string}  provider is null when no service could answer
     */
    public function suggest(string $text, ?float $latitude = null, ?float $longitude = null): array
    {
        $settings = config('curad.places');
        $text = mb_strtolower(trim(preg_replace('/\s+/', ' ', $text)));

        if (mb_strlen($text) < $settings['min_letters']) {
            return ['places' => [], 'provider' => null];
        }

        // Places near each other share an answer: the point is rounded to about 11 km.
        $near = $latitude !== null && $longitude !== null ? sprintf('%.1f,%.1f', $latitude, $longitude) : 'anywhere';
        $remembered = 'places:answer:' . md5($text . '|' . $near);

        if ($answer = Cache::get($remembered)) {
            return $answer;
        }

        foreach ($settings['order'] as $name) {
            if (! $this->available($name)) {
                continue;
            }

            try {
                $this->count($name);
                $places = $this->provider($name)->search($text, $latitude, $longitude, (int) $settings['results']);
            } catch (PlaceLimitReached $limit) {
                // Used up for today: leave it alone until midnight. Asked too quickly: a short rest.
                $limit->forToday
                    ? Cache::put("places:full:{$name}:" . today()->toDateString(), true, now()->endOfDay())
                    : Cache::put("places:resting:{$name}", true, now()->addSeconds(15));

                continue;
            } catch (\Throwable $problem) {
                report($problem);
                Cache::put("places:resting:{$name}", true, now()->addMinutes(3));

                continue;
            }

            // The map data often holds one street as several pieces; each address is listed once.
            $answer = [
                'places' => collect($places)->map(fn (array $place) => $this->tidy($place))->unique(fn (array $place) => mb_strtolower($place['label']))->values()->all(),
                'provider' => $name,
            ];
            Cache::put($remembered, $answer, now()->addDay());

            return $answer;
        }

        return ['places' => [], 'provider' => null];
    }

    /** How many questions each service has been asked today, and its daily limit (0 = none set). */
    public function usageToday(): array
    {
        $usage = [];

        foreach (config('curad.places.order') as $name) {
            $usage[$name] = [
                'asked' => (int) Cache::get($this->counter($name), 0),
                'limit' => (int) config("curad.places.{$name}.daily_limit"),
                'available' => $this->available($name),
            ];
        }

        return $usage;
    }

    /** May this service be asked right now? Not if it is unknown, has no key, is resting, or is used up for today. */
    public function available(string $name): bool
    {
        $settings = config("curad.places.{$name}");

        if (! isset(self::PROVIDERS[$name]) || ! is_array($settings) || (array_key_exists('key', $settings) && blank($settings['key']))) {
            return false;
        }

        if (Cache::has("places:resting:{$name}") || Cache::has("places:full:{$name}:" . today()->toDateString())) {
            return false;
        }

        $limit = (int) ($settings['daily_limit'] ?? 0);

        return $limit === 0 || (int) Cache::get($this->counter($name), 0) < $limit;
    }

    /** Notes one more question to a service against today's allowance. */
    public function count(string $name): void
    {
        Cache::add($this->counter($name), 0, now()->endOfDay());
        Cache::increment($this->counter($name));
    }

    private function counter(string $name): string
    {
        return "places:asked:{$name}:" . today()->toDateString();
    }

    private function provider(string $name): PlaceProvider
    {
        $class = self::PROVIDERS[$name];

        $http = Http::timeout((int) config('curad.places.timeout'))
            // Some Windows installs of PHP ship without the list of trusted
            // certificate authorities; CURAD_CA_BUNDLE points at one.
            ->withOptions(['verify' => config('curad.ca_bundle') ?: true])
            ->withHeaders(['User-Agent' => 'CuraD/1.0 (' . config('app.url') . ')']);

        return new $class($http, config("curad.places.{$name}") + ['country' => config('curad.places.country')]);
    }

    /** Every address in this app is in one country, so its name on the end of each line is only noise. */
    private function tidy(array $place): array
    {
        foreach (['label', 'subtitle'] as $field) {
            $place[$field] = trim(preg_replace('/,?\s*Nigeria$/i', '', (string) ($place[$field] ?? '')), ', ');
        }

        $place['latitude'] = isset($place['latitude']) ? round((float) $place['latitude'], 6) : null;
        $place['longitude'] = isset($place['longitude']) ? round((float) $place['longitude'], 6) : null;

        return $place;
    }
}
