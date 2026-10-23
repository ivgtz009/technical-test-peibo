<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\OrderService;
use App\Domain\Money;
use App\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;

final class OrderController
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $errors = [];
        $customerId = $request->body['customer_id'] ?? null;
        $items = $request->body['items'] ?? null;

        if (!is_int($customerId) || $customerId <= 0) {
            $errors['customer_id'] = 'Customer id must be a positive integer';
        }

        if (!is_array($items) || $items === []) {
            $errors['items'] = 'Items must be a non-empty array';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        /** @var int $customerId */
        /** @var list<array{product_id: mixed, quantity: mixed}> $items */
        $orderId = $this->orders->create($customerId, $items);

        return Response::json(['id' => $orderId], 201);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): Response
    {
        return Response::json($this->serialize($this->orders->get((int) $params['id'])));
    }

    /**
     * @param array<string, string> $params
     */
    public function confirm(Request $request, array $params): Response
    {
        $this->orders->confirm((int) $params['id']);

        return Response::json(['status' => 'confirmed']);
    }

    /**
     * @param array<string, string> $params
     */
    public function cancel(Request $request, array $params): Response
    {
        $this->orders->cancel((int) $params['id']);

        return Response::json(['status' => 'cancelled']);
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $filters = [];
        if (isset($request->query['status'])) {
            $filters['status'] = $request->query['status'];
        }
        if (isset($request->query['customer_id'])) {
            $filters['customer_id'] = (int) $request->query['customer_id'];
        }

        $page = max(1, (int) ($request->query['page'] ?? 1));
        $limit = min(100, max(1, (int) ($request->query['limit'] ?? 20)));

        $result = $this->orders->list($filters, $page, $limit);

        return Response::paginated(
            array_map([$this, 'serialize'], $result['items']),
            [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
            ],
        );
    }

    /**
     * @param array{id: int, customer_id: int, status: \App\Domain\OrderStatus, total_cents: int, created_at: string, items: list<array{product_id: int, quantity: int, unit_price_cents: int}>} $order
     * @return array<string, mixed>
     */
    private function serialize(array $order): array
    {
        return [
            'id' => $order['id'],
            'customer_id' => $order['customer_id'],
            'status' => $order['status']->value,
            'total' => Money::fromCents($order['total_cents'])->toDecimalString(),
            'created_at' => $order['created_at'],
            'items' => array_map(
                static fn (array $i): array => [
                    'product_id' => $i['product_id'],
                    'quantity' => $i['quantity'],
                    'unit_price' => Money::fromCents($i['unit_price_cents'])->toDecimalString(),
                ],
                $order['items'],
            ),
        ];
    }
}
