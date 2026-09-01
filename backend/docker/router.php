<?php

declare(strict_types=1);

/*
 * Front-controller router for PHP's built-in web server (local / Compose only).
 *
 * `php artisan serve` wraps the same server but funnels its access log through
 * the Artisan command's own output, which lands on stdout interleaved with the
 * application's JSON log lines — and `-q` silences both. Driving `php -S`
 * directly keeps the two streams separate:
 *
 *   stdout -> structured JSON application logs, one object per line
 *   stderr -> the dev server's own lifecycle and access lines
 *
 * so a log shipper reading stdout sees nothing but valid JSON.
 *
 * Production runs php-fpm instead (APP_SERVER=fpm); this file is not used there.
 */

$publicPath = dirname(__DIR__).'/public';
$uri = urldecode((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Let the server handle existing static files itself.
if ($uri !== '/' && is_file($publicPath.$uri)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicPath.'/index.php';

require_once $publicPath.'/index.php';
