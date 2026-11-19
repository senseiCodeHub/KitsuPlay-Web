<?php
/**
 * KitsuPlay Web — front controller.
 *
 * Every request lands here (see .htaccess); the router decides whether it is a
 * page, an API endpoint or a 404. Nothing outside `assets/` and `storage/` is
 * web-reachable.
 */

declare(strict_types=1);

define('APP_START', microtime(true));
define('ROOT_DIR', __DIR__);
define('APP_DIR', __DIR__ . '/app');

require APP_DIR . '/bootstrap.php';

$path = request_path();

try {
    route($path);
} catch (Throwable $error) {
    log_error($error);
    if (wants_json()) {
        json_out(['ok' => false, 'error' => 'Something went wrong on the server.'], 500);
    }
    http_response_code(500);
    render('error', [
        'title' => 'Something went wrong',
        'message' => 'This page could not be loaded. Please try again.',
    ]);
}
