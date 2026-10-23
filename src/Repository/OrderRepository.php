<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Money;
use App\Domain\OrderStatus;
use mysqli;

final class OrderRepository
{
    public function __construct(private readonly mysqli $conn)
    {
    }

    /**
     * @param list<array{product_id: int, quantity: int, unit_price_cents: int}> $items
     */
    public function create(int $customerId, int $totalCents, array $items): int
    {
        $total = Money::fromCents($totalCents)->toDecimalString();

        $stmt = $this->conn->prepare(
            "INSERT INTO orders (customer_id, status, total) VALUES (?, 'pending', ?)"
        );
        $stmt->bind_param('is', $customerId, $total);
        $stmt->execute();
        $orderId = (int) $this->conn->insert_id;

        $itemStmt = $this->conn->prepare(
            'INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)'
        );

        foreach ($items as $item) {
            $unitPrice = Money::fromCents($item['unit_price_cents'])->toDecimalString();
            $itemStmt->bind_param(
                'iiis',
                $orderId,
                $item['product_id'],
                $item['quantity'],
                $unitPrice,
            );
            $itemStmt->execute();
        }

        return $orderId;
    }

    /**
     * @return array{id: int, customer_id: int, status: OrderStatus, total_cents: int, created_at: string, items: list<array{product_id: int, quantity: int, unit_price_cents: int}>}|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT id, customer_id, status, total, created_at FROM orders WHERE id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'customer_id' => (int) $row['customer_id'],
            'status' => OrderStatus::fromDatabase((string) $row['status']),
            'total_cents' => Money::fromDecimalString((string) $row['total'])->cents,
            'created_at' => (string) $row['created_at'],
            'items' => $this->findItems((int) $row['id']),
        ];
    }

    /**
     * @return list<array{product_id: int, quantity: int, unit_price_cents: int}>
     */
    private function findItems(int $orderId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT product_id, quantity, unit_price FROM order_items WHERE order_id = ? ORDER BY id'
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'product_id' => (int) $row['product_id'],
                'quantity' => (int) $row['quantity'],
                'unit_price_cents' => Money::fromDecimalString((string) $row['unit_price'])->cents,
            ];
        }

        return $items;
    }

    public function confirm(int $id): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE orders SET status = 'confirmed' WHERE id = ? AND status = 'pending'"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->affected_rows === 1;
    }

    public function cancel(int $id): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'pending'"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->affected_rows === 1;
    }

    /**
     * @param array{status?: string, customer_id?: int} $filters
     * @return array{items: list<array{id: int, customer_id: int, status: OrderStatus, total_cents: int, created_at: string, items: list<array{product_id: int, quantity: int, unit_price_cents: int}>}>, total: int}
     */
    public function list(array $filters, int $page, int $limit): array
    {
        $where = [];
        $types = '';
        $values = [];

        if (isset($filters['status'])) {
            $where[] = 'status = ?';
            $types .= 's';
            $values[] = $filters['status'];
        }
        if (isset($filters['customer_id'])) {
            $where[] = 'customer_id = ?';
            $types .= 'i';
            $values[] = $filters['customer_id'];
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        // Count
        $countStmt = $this->conn->prepare("SELECT COUNT(*) AS c FROM orders{$whereSql}");
        if ($values !== []) {
            $this->bindDynamic($countStmt, $types, $values);
        }
        $countStmt->execute();
        $countRow = $countStmt->get_result()->fetch_assoc();
        $total = (int) ($countRow['c'] ?? 0);

        // List
        $offset = ($page - 1) * $limit;
        $sql = "SELECT id, customer_id, status, total, created_at FROM orders{$whereSql} ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);

        $listTypes = $types . 'ii';
        $listValues = $values;
        $listValues[] = $limit;
        $listValues[] = $offset;

        $this->bindDynamic($stmt, $listTypes, $listValues);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'id' => (int) $row['id'],
                'customer_id' => (int) $row['customer_id'],
                'status' => OrderStatus::fromDatabase((string) $row['status']),
                'total_cents' => Money::fromDecimalString((string) $row['total'])->cents,
                'created_at' => (string) $row['created_at'],
                'items' => $this->findItems((int) $row['id']),
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param \mysqli_stmt $stmt
     * @param list<mixed> $values
     */
    private function bindDynamic(\mysqli_stmt $stmt, string $types, array $values): void
    {
        $refs = [];
        foreach ($values as $i => $value) {
            $refs[$i] = &$values[$i];
        }
        $stmt->bind_param($types, ...$refs);
    }
}
