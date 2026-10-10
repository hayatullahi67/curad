<?php

/*
|--------------------------------------------------------------------------
| CuraD marketplace settings
|--------------------------------------------------------------------------
|
| The numbers the business decides, kept in one place so they can change
| without touching code. Each one can also be set in .env.
|
*/

return [

    // Path to a CA certificate bundle for outgoing HTTPS calls, only needed
    // where PHP has none configured (typical on a Windows development PC).
    'ca_bundle' => env('CURAD_CA_BUNDLE'),

    'marketplace' => [
        // Extra percentage the customer pays on top of the products. 0 = none.
        'service_fee_percent' => (float) env('CURAD_SERVICE_FEE_PERCENT', 0),

        // CuraD's share of what a pharmacy sells online.
        'commission_percent' => (float) env('CURAD_COMMISSION_PERCENT', 5),

        // A pharmacy that has not answered a new order after this many hours
        // loses it: the stock is released and the customer is told.
        'confirmation_hours' => (int) env('CURAD_CONFIRMATION_HOURS', 24),

        // Most units of one product a customer can put in the cart.
        'max_quantity' => (int) env('CURAD_MAX_QUANTITY', 20),

        // An order is never split across more pharmacies than this.
        'max_pharmacies' => (int) env('CURAD_MAX_PHARMACIES', 4),
    ],

    // Road distances (how far it is to drive) come from a routing service
    // in the OSRM format. The default is the free public one, which is fine
    // for testing; a busy live site should point this at its own or a paid one.
    'routing' => [
        'enabled' => (bool) env('CURAD_ROAD_DISTANCE', true),
        'url' => env('CURAD_ROUTING_URL', 'https://router.project-osrm.org'),
        'timeout' => (int) env('CURAD_ROUTING_TIMEOUT', 6),
        // A road distance between two places is remembered for this long.
        'remember_days' => (int) env('CURAD_ROUTING_REMEMBER_DAYS', 30),
    ],

    // Address suggestions while someone types. The services are asked in
    // this order: when the first has used up its allowance for the day, or
    // cannot be reached, the next one answers. To move to Google later, add
    // a provider for it (app/Services/Maps/Places) and put its name first.
    'places' => [
        'order' => array_values(array_filter(array_map('trim', explode(',', (string) env('CURAD_PLACES_ORDER', 'geoapify,locationiq,photon'))))),

        // Only addresses in this country are suggested.
        'country' => env('CURAD_PLACES_COUNTRY', 'ng'),

        // Nothing is asked until this many letters are typed.
        'min_letters' => (int) env('CURAD_PLACES_MIN_LETTERS', 3),

        // How many suggestions to show, and how long to wait for a service.
        'results' => (int) env('CURAD_PLACES_RESULTS', 6),
        'timeout' => (int) env('CURAD_PLACES_TIMEOUT', 5),

        // daily_limit is where this app stops asking a service each day,
        // set a little under what its free plan allows. 0 means no limit.
        'geoapify' => [
            'key' => env('CURAD_GEOAPIFY_KEY'),
            'daily_limit' => (int) env('CURAD_GEOAPIFY_DAILY_LIMIT', 2900),
        ],
        'locationiq' => [
            'key' => env('CURAD_LOCATIONIQ_KEY'),
            'daily_limit' => (int) env('CURAD_LOCATIONIQ_DAILY_LIMIT', 4900),
        ],
        'photon' => [
            'url' => env('CURAD_PHOTON_URL', 'https://photon.komoot.io'),
            'daily_limit' => (int) env('CURAD_PHOTON_DAILY_LIMIT', 0),
        ],
    ],

    'delivery' => [
        // fee = (base + per_km x kilometres + per_minute x minutes) x multiplier
        //
        // Kilometres and minutes are for the rider's whole route by road:
        // every pharmacy on the order, then the customer. Example: 10 km in
        // 30 minutes = 700 + 1,500 + 300 = 2,500 at a normal time, 3,000
        // when busy, 3,750 when very busy.
        'base_fee' => (float) env('CURAD_DELIVERY_BASE_FEE', 700),
        'per_km' => (float) env('CURAD_DELIVERY_PER_KM', 150),
        'per_minute' => (float) env('CURAD_DELIVERY_PER_MINUTE', 10),

        // No delivery costs less than this, however short the trip.
        'minimum_fee' => (float) env('CURAD_DELIVERY_MINIMUM_FEE', 1000),

        // What the fee is multiplied by at each level of demand.
        'multipliers' => [
            'normal' => (float) env('CURAD_DELIVERY_MULTIPLIER_NORMAL', 1.0),
            'busy' => (float) env('CURAD_DELIVERY_MULTIPLIER_BUSY', 1.2),
            'very_busy' => (float) env('CURAD_DELIVERY_MULTIPLIER_VERY_BUSY', 1.5),
        ],

        // When it counts as busy and very busy. Days are 1 (Monday) to 7
        // (Sunday); times are 24-hour, in the app's time zone (Lagos). A
        // moment inside a very-busy window is very busy even if it is also
        // inside a busy one. Any time not listed is normal.
        'busy_hours' => [
            ['days' => [1, 2, 3, 4, 5], 'from' => '07:00', 'to' => '10:00'],
            ['days' => [1, 2, 3, 4, 5], 'from' => '16:00', 'to' => '20:00'],
        ],
        'very_busy_hours' => [
            // None yet. Example: ['days' => [5], 'from' => '17:00', 'to' => '19:00'],
        ],

        // Charged when the customer's or a pharmacy's map position is not
        // known, so there is no distance or time to work from.
        'flat_fee' => (float) env('CURAD_DELIVERY_FLAT_FEE', 1500),

        // Used only when the routing service cannot be reached: the drive is
        // estimated as the straight line times road_factor, at this speed.
        'road_factor' => (float) env('CURAD_DELIVERY_ROAD_FACTOR', 1.3),
        'fallback_speed_kmh' => (float) env('CURAD_DELIVERY_FALLBACK_SPEED_KMH', 25),
    ],

];
