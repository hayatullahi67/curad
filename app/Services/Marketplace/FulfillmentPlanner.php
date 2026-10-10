<?php

namespace App\Services\Marketplace;

use App\Models\Medication;
use App\Services\Maps\RoadDistance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Decides which pharmacies supply a cart (PRD section 13).
 *
 *   1. One pharmacy that has everything is preferred.
 *   2. Otherwise the smallest group of pharmacies that covers the cart.
 *   3. Among equally small groups: the lowest cost to the customer, counting
 *      product prices plus the delivery fee (delivery) or the travel
 *      distance (pickup).
 *
 * Only stock that is really free is counted: what is on the shelf minus
 * what is already promised to other online orders.
 */
class FulfillmentPlanner
{
    public function __construct(private DeliveryRouter $router, private RoadDistance $roads)
    {
    }

    /**
     * @param  Collection<string, int>  $wanted  product key => quantity
     * @return array{groups: array, unavailable: array, subtotal: float, service_fee: float, delivery: ?array, delivery_fee: float, total: float, signature: string, method: string}
     */
    public function plan(Collection $wanted, string $method, ?float $latitude = null, ?float $longitude = null): array
    {
        [$offers, $pharmacies] = $this->offers($wanted);

        $unavailable = [];
        $coverable = [];

        foreach ($wanted as $key => $quantity) {
            if (empty($offers[$key])) {
                $unavailable[$key] = $quantity;
            } else {
                $coverable[$key] = $quantity;
            }
        }

        // Every candidate pharmacy and the customer, asked about once, so that
        // comparing the possible groups below needs no further questions.
        if ($coverable && $latitude !== null && $longitude !== null) {
            $this->roads->warm([
                ...$pharmacies->filter(fn ($pharmacy) => $pharmacy->latitude !== null && $pharmacy->longitude !== null)
                    ->map(fn ($pharmacy) => [(float) $pharmacy->latitude, (float) $pharmacy->longitude])->values()->all(),
                [$latitude, $longitude],
            ]);
        }

        $best = $coverable ? $this->bestGroup($coverable, $offers, $pharmacies, $method, $latitude, $longitude) : null;

        // Too scattered to cover within the pharmacy limit: what could not
        // be placed is reported as unavailable rather than quietly dropped.
        foreach ($best['left_over'] ?? [] as $key) {
            $unavailable[$key] = $coverable[$key];
        }

        $groups = $best['groups'] ?? [];
        $subtotal = round(array_sum(array_column($groups, 'subtotal')), 2);
        $serviceFee = round($subtotal * config('curad.marketplace.service_fee_percent') / 100, 2);
        $delivery = $method === 'delivery' && $groups ? $best['delivery'] : null;
        $deliveryFee = $delivery['fee'] ?? 0.0;

        // The rider's stops, in visiting order.
        if ($delivery) {
            $position = array_flip($delivery['sequence']);
            usort($groups, fn ($a, $b) => $position[$a['pharmacy']->id] <=> $position[$b['pharmacy']->id]);
        }

        return [
            'method' => $method,
            'groups' => $groups,
            'unavailable' => $unavailable,
            'subtotal' => $subtotal,
            'service_fee' => $serviceFee,
            'delivery' => $delivery,
            'delivery_fee' => $deliveryFee,
            'total' => round($subtotal + $serviceFee + $deliveryFee, 2),
            'signature' => $this->signature($method, $groups, $serviceFee, $deliveryFee, $unavailable),
        ];
    }

    /**
     * Free stock for each wanted product at each pharmacy.
     *
     * @return array{0: array<string, array<int, Medication>>, 1: Collection}
     */
    private function offers(Collection $wanted): array
    {
        if ($wanted->isEmpty()) {
            return [[], collect()];
        }

        $rows = Medication::query()
            ->marketplace()
            ->whereHas('pharmacy', fn ($branch) => $branch->active())
            ->with('pharmacy.organization')
            ->whereIn(DB::raw('LOWER(TRIM(name))'), $wanted->keys()->all())
            ->where(function ($query) {
                $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today());
            })
            ->orderBy('price')
            ->orderBy('id')
            ->get();

        $offers = [];

        foreach ($rows as $row) {
            $key = mb_strtolower(trim($row->name));

            if ($row->availableStock() < (int) $wanted[$key]) {
                continue;
            }

            // Rows are cheapest first, so the first one seen per pharmacy wins.
            $offers[$key][$row->pharmacy_id] ??= $row;
        }

        return [$offers, $rows->pluck('pharmacy')->unique('id')->keyBy('id')];
    }

    private function bestGroup(array $coverable, array $offers, Collection $pharmacies, string $method, ?float $latitude, ?float $longitude): array
    {
        $keys = array_keys($coverable);
        $limit = max(1, (int) config('curad.marketplace.max_pharmacies'));

        // Pharmacies that stock the most of the cart are tried first, and
        // only the strongest dozen, to keep the search quick.
        $coverage = [];
        foreach ($keys as $key) {
            foreach (array_keys($offers[$key]) as $pharmacyId) {
                $coverage[$pharmacyId] = ($coverage[$pharmacyId] ?? 0) + 1;
            }
        }
        arsort($coverage);
        $candidates = array_slice(array_keys($coverage), 0, 12);

        for ($size = 1; $size <= min($limit, count($candidates), count($keys)); $size++) {
            $best = null;

            foreach ($this->combinations($candidates, $size) as $combination) {
                $option = $this->assign($combination, $coverable, $offers, $pharmacies, $method, $latitude, $longitude);

                if ($option && ($best === null || $option['score'] < $best['score'])) {
                    $best = $option;
                }
            }

            if ($best) {
                return $best + ['left_over' => []];
            }
        }

        // No group within the limit covers everything: take the pharmacies
        // that cover the most, and report the rest.
        $chosen = [];
        $remaining = $keys;

        while ($remaining && count($chosen) < $limit) {
            $pick = null;
            $pickCount = 0;

            foreach ($candidates as $pharmacyId) {
                $count = count(array_filter($remaining, fn ($key) => isset($offers[$key][$pharmacyId])));
                if ($count > $pickCount) {
                    [$pick, $pickCount] = [$pharmacyId, $count];
                }
            }

            if (! $pick) {
                break;
            }

            $chosen[] = $pick;
            $remaining = array_values(array_filter($remaining, fn ($key) => ! isset($offers[$key][$pick])));
        }

        $covered = array_diff_key($coverable, array_flip($remaining));
        $option = $this->assign($chosen, $covered, $offers, $pharmacies, $method, $latitude, $longitude);

        return ($option ?? ['groups' => [], 'delivery' => null]) + ['left_over' => $remaining];
    }

    /**
     * Gives each product to the cheapest pharmacy in the group. Returns null
     * when the group cannot supply everything, or has a pharmacy left idle.
     */
    private function assign(array $pharmacyIds, array $coverable, array $offers, Collection $pharmacies, string $method, ?float $latitude, ?float $longitude): ?array
    {
        $groups = [];

        foreach ($coverable as $key => $quantity) {
            $choice = null;

            foreach ($pharmacyIds as $pharmacyId) {
                $offer = $offers[$key][$pharmacyId] ?? null;

                if ($offer && ($choice === null || (float) $offer->price < (float) $choice->price)) {
                    $choice = $offer;
                }
            }

            if (! $choice) {
                return null;
            }

            $lineTotal = round((float) $choice->price * $quantity, 2);

            $groups[$choice->pharmacy_id] ??= ['pharmacy' => $pharmacies[$choice->pharmacy_id], 'lines' => [], 'subtotal' => 0.0];
            $groups[$choice->pharmacy_id]['lines'][] = [
                'product_key' => $key,
                'offer' => $choice,
                'quantity' => (int) $quantity,
                'unit_price' => (float) $choice->price,
                'line_total' => $lineTotal,
            ];
            $groups[$choice->pharmacy_id]['subtotal'] = round($groups[$choice->pharmacy_id]['subtotal'] + $lineTotal, 2);
        }

        if (count($groups) !== count($pharmacyIds)) {
            return null;
        }

        $groups = array_values($groups);
        $chosen = collect($groups)->pluck('pharmacy');
        $delivery = null;
        $score = array_sum(array_column($groups, 'subtotal'));

        if ($method === 'delivery') {
            $delivery = $this->router->quote($chosen, $latitude, $longitude);
            $score += $delivery['fee'];
        } elseif ($latitude !== null && $longitude !== null) {
            // Pickup: the further the customer must travel, the worse the option.
            foreach ($groups as &$group) {
                $pharmacy = $group['pharmacy'];

                if ($pharmacy->latitude !== null && $pharmacy->longitude !== null) {
                    $trip = $this->roads->between($latitude, $longitude, (float) $pharmacy->latitude, (float) $pharmacy->longitude);
                    $group['distance_km'] = $trip['km'];
                    $group['distance_by_road'] = $trip['road'];
                    $score += $group['distance_km'] * config('curad.delivery.per_km');
                }
            }
            unset($group);
        }

        return ['groups' => $groups, 'delivery' => $delivery, 'score' => $score];
    }

    /** Every way of choosing $size pharmacies from the list. */
    private function combinations(array $items, int $size): array
    {
        if ($size === 0) {
            return [[]];
        }

        if (count($items) < $size) {
            return [];
        }

        $first = array_shift($items);
        $with = array_map(fn ($rest) => array_merge([$first], $rest), $this->combinations($items, $size - 1));

        return array_merge($with, $this->combinations($items, $size));
    }

    /**
     * A fingerprint of what the customer was shown. If stock or a price
     * changes before they press "Place order", the fingerprint no longer
     * matches and they are shown the new plan instead of being charged.
     */
    private function signature(string $method, array $groups, float $serviceFee, float $deliveryFee, array $unavailable): string
    {
        $parts = [$method, $serviceFee, $deliveryFee, array_keys($unavailable)];

        foreach ($groups as $group) {
            foreach ($group['lines'] as $line) {
                $parts[] = [$group['pharmacy']->id, $line['offer']->id, $line['quantity'], $line['unit_price']];
            }
        }

        return md5(json_encode($parts));
    }
}
