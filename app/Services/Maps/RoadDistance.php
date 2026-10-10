<?php

namespace App\Services\Maps;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * How far it is to drive between two map points, and how long it takes.
 *
 * Both come from a routing service that speaks the OSRM "table" format
 * (the free public one by default; set CURAD_ROUTING_URL to use another).
 * Every answer is remembered, so the same pair of places is only ever asked
 * about once. The driving time is the service's estimate from road speeds;
 * it does not know about live traffic.
 *
 * When the service cannot be reached, or has no road between two points,
 * the trip is estimated from the straight line instead, and reported as an
 * estimate so the page can say "about".
 */
class RoadDistance
{
    /** Most places sent in one question to the routing service. */
    private const BATCH = 80;

    /** Answers already fetched during this request: key => ['km' => float, 'min' => float]. */
    private static array $known = [];

    /** Set when the service failed during this request, so it is not asked again and again. */
    private static bool $down = false;

    /**
     * The trip from one point to each of several places.
     *
     * @param  array<int|string, array{0: float, 1: float}>  $places  key => [latitude, longitude]
     * @return array<int|string, array{km: float, min: float, road: bool}>
     */
    public function fromPoint(float $latitude, float $longitude, array $places): array
    {
        $origin = [$latitude, $longitude];
        $this->learn([$origin], array_values($places));

        $result = [];

        foreach ($places as $key => $place) {
            $result[$key] = $this->answer($origin, $place);
        }

        return $result;
    }

    /**
     * Kilometres by road, minutes of driving, and whether both are real
     * (true) or an estimate (false).
     *
     * @return array{km: float, min: float, road: bool}
     */
    public function between(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): array
    {
        $from = [$latitudeA, $longitudeA];
        $to = [$latitudeB, $longitudeB];
        $this->learn([$from], [$to]);

        return $this->answer($from, $to);
    }

    /**
     * Fetches, in one go, every trip between a set of points, in both
     * directions. Call this before many between() calls on the same points
     * (planning a rider's route) so they are all answered from memory.
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    public function warm(array $points): void
    {
        $this->learn($points, $points);
    }

    /** Straight-line distance between two map points, in kilometres. */
    public static function straightLine(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * The real trip if we have it. Otherwise an estimate: the straight line
     * stretched by the usual amount, driven at the usual city speed.
     */
    private function answer(array $from, array $to): array
    {
        $trip = self::$known[$this->key($from, $to)] ?? null;

        if ($trip !== null) {
            return ['km' => round($trip['km'], 1), 'min' => round($trip['min'], 1), 'road' => true];
        }

        $kilometres = self::straightLine((float) $from[0], (float) $from[1], (float) $to[0], (float) $to[1]) * (float) config('curad.delivery.road_factor');
        $speed = max(1.0, (float) config('curad.delivery.fallback_speed_kmh'));

        return ['km' => round($kilometres, 1), 'min' => round($kilometres / $speed * 60, 1), 'road' => false];
    }

    /** Makes sure the trip from every origin to every destination is known, asking only for what is missing. */
    private function learn(array $origins, array $destinations): void
    {
        if (! config('curad.routing.enabled')) {
            return;
        }

        foreach ($origins as $origin) {
            $missing = [];

            foreach ($destinations as $destination) {
                $key = $this->key($origin, $destination);

                if (array_key_exists($key, self::$known)) {
                    continue;
                }

                $remembered = Cache::get($key);

                if (is_array($remembered) && isset($remembered['km'], $remembered['min'])) {
                    self::$known[$key] = ['km' => (float) $remembered['km'], 'min' => (float) $remembered['min']];
                } else {
                    $missing[$key] = $destination;
                }
            }

            foreach (array_chunk($missing, self::BATCH, true) as $batch) {
                $this->ask($origin, $batch);
            }
        }
    }

    /** One question to the routing service: from this origin to these places. */
    private function ask(array $origin, array $places): void
    {
        if (self::$down || Cache::has('road:down')) {
            return;
        }

        $keys = array_keys($places);
        $coordinates = collect([$origin, ...array_values($places)])
            ->map(fn (array $point) => round((float) $point[1], 6) . ',' . round((float) $point[0], 6))
            ->implode(';');

        try {
            $response = Http::timeout((int) config('curad.routing.timeout'))
                // A connection that fails for a moment (a slow name lookup, a
                // dropped packet) is tried once more before giving up.
                ->retry(2, 300, throw: false)
                // Some Windows installs of PHP ship without the list of trusted
                // certificate authorities; CURAD_CA_BUNDLE points at one.
                ->withOptions(['verify' => config('curad.ca_bundle') ?: true])
                ->withHeaders(['User-Agent' => 'CuraD/1.0 (' . config('app.url') . ')'])
                ->get(rtrim((string) config('curad.routing.url'), '/') . '/table/v1/driving/' . $coordinates, [
                    'sources' => '0',
                    'destinations' => implode(';', range(1, count($places))),
                    'annotations' => 'distance,duration',
                ]);

            $ok = $response->successful() && $response->json('code') === 'Ok';
            $metres = $ok ? $response->json('distances.0') : null;
            $seconds = $ok ? $response->json('durations.0') : null;
        } catch (\Throwable $problem) {
            report($problem);
            $metres = $seconds = null;
        }

        if (! is_array($metres) || ! is_array($seconds)) {
            // Give the service a couple of minutes before trying again, so
            // pages do not each wait for it while it is down.
            self::$down = true;
            Cache::put('road:down', true, now()->addMinutes(2));

            return;
        }

        $keepFor = now()->addDays((int) config('curad.routing.remember_days'));

        foreach ($keys as $index => $key) {
            // The service answers null when no road joins the two points.
            if (($metres[$index] ?? null) !== null && ($seconds[$index] ?? null) !== null) {
                self::$known[$key] = ['km' => $metres[$index] / 1000, 'min' => $seconds[$index] / 60];
                Cache::put($key, self::$known[$key], $keepFor);
            }
        }
    }

    /** Points are rounded to about 11 metres, so tiny GPS wobbles reuse the same answer. */
    private function key(array $from, array $to): string
    {
        return sprintf('trip:%.4f,%.4f>%.4f,%.4f', $from[0], $from[1], $to[0], $to[1]);
    }
}
