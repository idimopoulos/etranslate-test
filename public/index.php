<?php

declare(strict_types=1);

use App\Controller\CallbackController;
use App\Controller\RequestController;
use App\Controller\SendController;
use App\View;

require __DIR__ . '/../vendor/autoload.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

try {
  match (TRUE) {
    $method === 'GET' && $path === '/' => (new SendController())->form(),
    $method === 'POST' && $path === '/send' => (new SendController())->submit(),
    $method === 'GET' && $path === '/requests' => (new RequestController())->list(),
    $method === 'GET' && (bool) preg_match('#^/requests/(\d+)$#', $path, $m) => (new RequestController())->show((int) $m[1]),
    $method === 'GET' && $path === '/domains' => (new RequestController())->domains(),
    $method === 'POST' && (bool) preg_match('#^/callback/(\d+)/([A-Za-z0-9_-]{43})$#', $path, $m) => (new CallbackController())->success((int) $m[1], $m[2]),
    $method === 'POST' && (bool) preg_match('#^/error/(\d+)/([A-Za-z0-9_-]{43})$#', $path, $m) => (new CallbackController())->error((int) $m[1], $m[2]),
    default => View::render('notfound', [], 404),
  };
}
catch (\Throwable $e) {
  error_log((string) $e);
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'Internal error: ' . $e->getMessage();
}
