<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\OrderCalculator;
use App\Domain\OrderStatus;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use mysqli;

final class OrderService
{
    public function __construct(
        private readonly mysqli $conn,
        private readonly OrderRepository $orders,
        private readonly ProductRepository $products,
        private readonly OrderCalculator $calculator,
    ) {
    }

    /**
     * @param list<array{product_id: mixed, quantity: mixed}> $items
     */
    public function create(int $customerId, array $items): int
    {
        $ids = [];
        foreach ($items as $item) {
            if (isset($item['product_id']) && is_int($item['product_id'])) {
                $ids[] = $item['product_id'];
            }
        }

        $this->conn->begin_transaction();
        try {
            $products = $this->products->findByIds($ids);
            $calculated = $this->calculator->calculate($items, $products);
            $orderId = $this->orders->create($customerId, $calculated['total_cents'], $calculated['items']);
            $this->conn->commit();

            return $orderId;
        } catch (\Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function confirm(int $orderId): void
    {
        $this->conn->begin_transaction();
        try {
            $order = $this->orders->findById($orderId);

            if ($order === null) {
                throw new NotFoundException("Order {$orderId} not found");
            }

            if (!$order['status']->canBeConfirmed()) {
                throw new ConflictException("Order {$orderId} is already {$order['status']->value}");
            }

            $productIds = array_map(
                static fn (array $i): int => $i['product_id'],
                $order['items'],
            );
            $products = $this->products->findByIds($productIds);

            $currentStock = [];
            foreach ($products as $id => $p) {
                $currentStock[$id] = $p['stock'];
            }

            $this->calculator->assertStockAvailable($order['items'], $currentStock);

            if (!$this->orders->confirm($orderId)) {
                throw new ConflictException("Order {$orderId} could not be confirmed");
            }

            $stmt = $this->conn->prepare(
                'UPDATE products SET stock = stock - ? WHERE id = ?'
            );
            foreach ($order['items'] as $item) {
                $stmt->bind_param('ii', $item['quantity'], $item['product_id']);
                $stmt->execute();
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function cancel(int $orderId): void
    {
        $this->conn->begin_transaction();
        try {
            $order = $this->orders->findById($orderId);

            if ($order === null) {
                throw new NotFoundException("Order {$orderId} not found");
            }

            if (!$order['status']->canBeCancelled()) {
                throw new ConflictException("Order {$orderId} is already {$order['status']->value}");
            }

            if (!$this->orders->cancel($orderId)) {
                throw new ConflictException("Order {$orderId} could not be cancelled");
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * @return array{id: int, customer_id: int, status: OrderStatus, total_cents: int, created_at: string, items: list<array{product_id: int, quantity: int, unit_price_cents: int}>}
     */
    public function get(int $orderId): array
    {
        $order = $this->orders->findById($orderId);

        if ($order === null) {
            throw new NotFoundException("Order {$orderId} not found");
        }

        return $order;
    }

    /**
     * @param array{status?: string, customer_id?: int} $filters
     * @return array{items: list<array{id: int, customer_id: int, status: OrderStatus, total_cents: int, created_at: string, items: list<array{product_id: int, quantity: int, unit_price_cents: int}>}>, total: int}
     */
    public function list(array $filters, int $page, int $limit): array
    {
        return $this->orders->list($filters, $page, $limit);
    }
}
