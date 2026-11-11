<?php

declare(strict_types=1);

/*
 * Única puerta de entrada del aplicativo (front controller).
 * Local:  composer serve   →  http://localhost:8000
 */

// Con el servidor de PHP (php -S), los archivos que existen (css, js) se sirven directo.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require __DIR__ . '/../vendor/autoload.php';

use App\Core\App;
use App\Core\Router;

$router = new Router();
(require __DIR__ . '/../app/routes.php')($router);

(new App($router))
    ->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/')
    ->send();
