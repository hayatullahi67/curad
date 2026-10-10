<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// This app is always reached through CURA/.htaccess forwarding
// /CURA/whatever -> public/whatever -> here, since shared hosting can't
// point a subdirectory's document root at CURA/public directly. That
// rewrite chain leaves SCRIPT_NAME as the physical path
// (/CURA/public/index.php), but the browser's URL has no "/public" in it
// (/CURA/whatever) — so Symfony's Request::prepareBaseUrl(), which strips
// whatever directory SCRIPT_NAME resolves to from the URL to figure out
// the route path, can't find that prefix in the URL at all and falls back
// to treating the *entire* URL (including "/CURA") as the route path.
// Every route then 404s except the one coincidental case where the raw
// URL happens to equal the script path exactly. Rewriting SCRIPT_NAME here
// to drop "/public" fixes the prefix match for every environment this app
// runs in (local_router.php did the same thing by hand for the PHP
// built-in server; this covers real Apache/LiteSpeed too).
if (isset($_SERVER['SCRIPT_NAME']) && str_contains($_SERVER['SCRIPT_NAME'], '/public/')) {
    $_SERVER['SCRIPT_NAME'] = str_replace('/public/index.php', '/index.php', $_SERVER['SCRIPT_NAME']);
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
