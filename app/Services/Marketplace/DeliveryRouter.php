<?php

namespace App\Services\Marketplace;

use App\Services\Maps\RoadDistance;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Works out the rider's route and the delivery fee for one order: collect
 * from each pharmacy, then drop off at the customer.
 *
 *   fee = (base + kilometres x per_km + minutes x per_minute) x multiplier
 *
 * and never less than the minimum fee. Kilometres and minutes are for the
 * whole route by road. The multiplier is 1.0 normally and higher in the
 * busy hours set in config/curad.php. An order uses at most a handful of
 * pharmacies, so every visiting order is tried and the cheapest is kept.
 */
class DeliveryRouter
{
    public function __construct(private RoadDistance $roads)
    {
    }

    /**
     * @param  Collection<int, \App\Models\Pharmacy>  $pharmacies
     * @return array{fee: float, distance_km: ?float, minutes: ?int, multiplier: float, period: string, sequence: array<int, int>, exact: bool, by_road: bool}
     */
    public function quote(Collection $pharmacies, ?float $latitude, ?float $longitude, ?CarbonInterface $at = null): array
    {
        $settings = config('curad.delivery');
        $pharmacies = $pharmacies->values();
        $period = $this->period($at);

        $located = $latitude !== null && $longitude !== null
            && $pharmacies->every(fn ($pharmacy) => $pharmacy->latitude !== null && $pharmacy->longitude !== null);

        // Without a map position on both ends there is nothing to measure,
        // so one standard fee is charged, the same at any hour.
        if (! $located || $pharmacies->isEmpty()) {
            return [
                'fee' => round((float) $settings['flat_fee'], 2),
                'distance_km' => null,
                'minutes' => null,
                'multiplier' => 1.0,
                'period' => 'normal',
                'sequence' => $pharmacies->pluck('id')->all(),
                'exact' => false,
                'by_road' => false,
            ];
        }

        // One question to the routing service covers every leg of every possible route.
        $this->roads->warm([...$pharmacies->map(fn ($pharmacy) => [(float) $pharmacy->latitude, (float) $pharmacy->longitude])->all(), [$latitude, $longitude]]);

        $best = null;

        foreach ($this->orderings($pharmacies->all()) as $ordering) {
            $kilometres = 0.0;
            $minutes = 0.0;
            $byRoad = true;
            $previous = null;

            foreach ([...$ordering, null] as $stop) {
                if ($previous) {
                    // The last leg, after every pharmacy, is to the customer.
                    $leg = $this->roads->between(
                        (float) $previous->latitude, (float) $previous->longitude,
                        $stop ? (float) $stop->latitude : $latitude, $stop ? (float) $stop->longitude : $longitude,
                    );
                    $kilometres += $leg['km'];
                    $minutes += $leg['min'];
                    $byRoad = $byRoad && $leg['road'];
                }
                $previous = $stop;
            }

            $cost = $kilometres * $settings['per_km'] + $minutes * $settings['per_minute'];

            if ($best === null || $cost < $best['cost']) {
                $best = ['cost' => $cost, 'km' => $kilometres, 'min' => $minutes, 'ordering' => $ordering, 'by_road' => $byRoad];
            }
        }

        // What the customer is shown is what they are charged for: one decimal of a kilometre, whole minutes.
        $kilometres = round($best['km'], 1);
        $minutes = (int) ceil($best['min']);

        return [
            'fee' => $this->fee($kilometres, $minutes, $period['multiplier']),
            'distance_km' => $kilometres,
            'minutes' => $minutes,
            'multiplier' => $period['multiplier'],
            'period' => $period['key'],
            'sequence' => array_map(fn ($pharmacy) => $pharmacy->id, $best['ordering']),
            'exact' => true,
            // False when the routing service could not be reached and the distance and time are estimates.
            'by_road' => $best['by_road'],
        ];
    }

    /** (base + kilometres x per_km + minutes x per_minute) x multiplier, never below the minimum, in whole naira. */
    public function fee(float $kilometres, float $minutes, float $multiplier = 1.0): float
    {
        $settings = config('curad.delivery');
        $amount = ($settings['base_fee'] + $kilometres * $settings['per_km'] + $minutes * $settings['per_minute']) * $multiplier;

        return (float) round(max((float) $settings['minimum_fee'], $amount));
    }

    /**
     * How busy it is at a moment (now, unless told otherwise), from the
     * hours listed in config/curad.php. "Very busy" wins over "busy".
     *
     * @return array{key: string, label: string, multiplier: float}
     */
    public function period(?CarbonInterface $at = null): array
    {
        $at ??= now();
        $settings = config('curad.delivery');

        foreach (['very_busy' => 'Very busy', 'busy' => 'Busy'] as $key => $label) {
            foreach ((array) ($settings[$key . '_hours'] ?? []) as $window) {
                $onDay = in_array($at->dayOfWeekIso, (array) ($window['days'] ?? [1, 2, 3, 4, 5, 6, 7]), true);
                $time = $at->format('H:i');

                if ($onDay && $time >= $window['from'] && $time < $window['to']) {
                    return ['key' => $key, 'label' => $label, 'multiplier' => (float) $settings['multipliers'][$key]];
                }
            }
        }

        return ['key' => 'normal', 'label' => 'Normal', 'multiplier' => (float) $settings['multipliers']['normal']];
    }

    /** Every order in which the stops can be visited. */
    private function orderings(array $stops): array
    {
        if (count($stops) <= 1) {
            return [$stops];
        }

        $result = [];

        foreach ($stops as $index => $stop) {
            $rest = $stops;
            unset($rest[$index]);

            foreach ($this->orderings(array_values($rest)) as $tail) {
                $result[] = array_merge([$stop], $tail);
            }
        }

        return $result;
    }
}
