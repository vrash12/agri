<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Install only in public_html/lgu-public; private application files stay above it.
$base = dirname(__DIR__, 2);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// A partially provisioned host must never fall through to central office routes.
if (config('lgu_websites.domain') !== 'agritarlac.online') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('Retry-After: 300');
    echo 'LGU public website hosting is being prepared.';
    exit;
}

$response = $kernel->handle($request = Request::capture());
$response->send();
$kernel->terminate($request, $response);
