<?php

declare(strict_types=1);

use App\Application\OrderService;
use App\Domain\OrderCalculator;
use App\Exceptions\HttpException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Infrastructure\Database;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;

require __DIR__ . '/../vendor/autoload.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof HttpException) {
        $response = Response::error($e->getMessage(), $e->getStatusCode(), $e->getContext());
    } elseif ($e instanceof ValidationException) {
        $response = Response::error($e->getMessage(), 422, $e->getContext());
    } else {
        error_log((string) $e);
        $response = Response::error('Internal server error', 500);
    }
    $response->send();
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$db = Database::connectFromEnv();
$conn = $db->connection();

$productRepo = new ProductRepository($conn);
$orderRepo = new OrderRepository($conn);
$calculator = new OrderCalculator();
$orderService = new OrderService($conn, $orderRepo, $productRepo, $calculator);

$router = new Router();
$health = new HealthController();
$products = new ProductController($productRepo);
$orders = new OrderController($orderService);

$router->add('GET', '/health', [$health, 'index']);

$router->add('POST', '/products', [$products, 'store']);
$router->add('GET', '/products', [$products, 'index']);

$router->add('POST', '/orders', [$orders, 'store']);
$router->add('GET', '/orders', [$orders, 'index']);
$router->add('GET', '/orders/{id}', [$orders, 'show']);
$router->add('PATCH', '/orders/{id}/confirm', [$orders, 'confirm']);
$router->add('PATCH', '/orders/{id}/cancel', [$orders, 'cancel']);

try {
    $request = Request::fromGlobals();
    $response = $router->dispatch($request);
    $response->send();
} catch (Throwable $e) {
    throw $e;
}
