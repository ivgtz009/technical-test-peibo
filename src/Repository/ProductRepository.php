<?php

declare(strict_types=1);

namespace App\Repository;

use mysqli;

final class ProductRepository
{
    public function __construct(private readonly mysqli $conn)
    {
    }

    /**
     * @return list<array{id: int, name: string, price: string, stock: int}>
     */
    public function all(): array
    {
        $result = $this->conn->query('SELECT id, name, price, stock FROM products ORDER BY id');
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'price' => (string) $row['price'],
                'stock' => (int) $row['stock'],
            ];
        }

        return $rows;
    }

    public function create(string $name, string $price, int $stock): int
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO products (name, price, stock) VALUES (?, ?, ?)'
        );
        $stmt->bind_param('ssi', $name, $price, $stock);
        $stmt->execute();

        return (int) $this->conn->insert_id;
    }

    /**
     * @param list<int> $ids
     * @return array<int, array{id: int, price: string, stock: int}> indexado por id
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $stmt = $this->conn->prepare(
            "SELECT id, price, stock FROM products WHERE id IN ({$placeholders}) FOR UPDATE"
        );
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'price' => (string) $row['price'],
                'stock' => (int) $row['stock'],
            ];
        }

        return $products;
    }
}
