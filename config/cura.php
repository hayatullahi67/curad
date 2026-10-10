<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maintenance key
    |--------------------------------------------------------------------------
    |
    | cPanel hosting has no terminal, so /migrate and /clear-cache exist as
    | web routes. They only run when called with ?key=<this value>. Leaving
    | it empty switches both routes off entirely.
    |
    */

    'maintenance_key' => env('CURA_MAINTENANCE_KEY'),

];
