<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\OrderService;
use App\Domain\OrderCalculator;
use App\Domain\OrderStatus;
use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Infrastructure\Database;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use mysqli;
use PHPUnit\Framework\TestCase;

final class OrderConfirmationTest extends TestCase
{
    private mysqli $conn;
    private OrderService $service;
    private ProductRepository $products;

    protected function setUp(): void
    {
        $this->conn = Database::connectFromEnv()->connection();

        $this->conn->query('SET FOREIGN_KEY_CHECKS = 0');
        $this->conn->query('TRUNCATE TABLE order_items');
        $this->conn->query('TRUNCATE TABLE orders');
        $this->conn->query('TRUNCATE TABLE products');
        $this->conn->query('SET FOREIGN_KEY_CHECKS = 1');

        $this->products = new ProductRepository($this->conn);
        $orders = new OrderRepository($this->conn);

        $this->service = new OrderService(
            $this->conn,
            $orders,
            $this->products,
            new OrderCalculator(),
        );
    }

    protected function tearDown(): void
    {
        $this->conn->rollback();
        $this->conn->close();
    }

    public function testConfirmDecrementsStockAndRejectsSecondConfirmation(): void
    {
        $productId = $this->products->create('Widget', '10.50', 5);

        $orderId = $this->service->create(1, [
            ['product_id' => $productId, 'quantity' => 2],
        ]);

        $order = $this->service->get($orderId);
        self::assertSame(OrderStatus::Pending, $order['status']);
        self::assertSame(2100, $order['total_cents']);

        $this->service->confirm($orderId);

        $confirmed = $this->service->get($orderId);
        self::assertSame(OrderStatus::Confirmed, $confirmed['status']);

        $product = $this->products->findByIds([$productId])[$productId];
        self::assertSame(3, $product['stock']);

        $this->expectException(ConflictException::class);
        $this->service->confirm($orderId);
    }

    public function testConfirmFailsWhenStockIsNoLongerSufficient(): void
    {
        $productId = $this->products->create('Scarce', '5.00', 1);

        $orderId = $this->service->create(1, [
            ['product_id' => $productId, 'quantity' => 1],
        ]);

        $stmt = $this->conn->prepare('UPDATE products SET stock = 0 WHERE id = ?');
        $stmt->bind_param('i', $productId);
        $stmt->execute();

        $this->expectException(ValidationException::class);
        $this->service->confirm($orderId);

        $order = $this->service->get($orderId);
        self::assertSame(OrderStatus::Pending, $order['status']);
    }

    public function testCancelPendingOrderSucceedsAndCancelConfirmedFails(): void
    {
        $productId = $this->products->create('Widget', '10.00', 5);

        $pendingId = $this->service->create(1, [
            ['product_id' => $productId, 'quantity' => 1],
        ]);
        $this->service->cancel($pendingId);
        self::assertSame(OrderStatus::Cancelled, $this->service->get($pendingId)['status']);

        $confirmedId = $this->service->create(1, [
            ['product_id' => $productId, 'quantity' => 1],
        ]);
        $this->service->confirm($confirmedId);

        $this->expectException(ConflictException::class);
        $this->service->cancel($confirmedId);
    }
}
