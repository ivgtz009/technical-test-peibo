<?php

declare(strict_types=1);

namespace App\Domain;

use App\Exceptions\ValidationException;

final class OrderCalculator
{
    /**
     * @param list<array{product_id: mixed, quantity: mixed}> $items
     * @param array<int, array{id: int, price: string, stock: int}> $products indexado por product_id
     * @return array{
     *     total_cents: int,
     *     items: list<array{product_id: int, quantity: int, unit_price_cents: int}>
     * }
     *
     * @throws ValidationException
     */
    public function calculate(array $items, array $products): array
    {
        if ($items === []) {
            throw new ValidationException(['items' => 'At least one item is required']);
        }

        $errors = [];
        $totalCents = 0;
        $normalized = [];

        foreach ($items as $index => $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? null;

            if (!is_int($productId) || $productId <= 0) {
                $errors["items.{$index}.product_id"] = 'Product id must be a positive integer';
                continue;
            }

            if (!is_int($quantity) || $quantity <= 0) {
                $errors["items.{$index}.quantity"] = 'Quantity must be a positive integer';
                continue;
            }

            if (!isset($products[$productId])) {
                $errors["items.{$index}.product_id"] = "Product {$productId} does not exist";
                continue;
            }

            $product = $products[$productId];

            if ($product['stock'] < $quantity) {
                $errors["items.{$index}.quantity"] = "Insufficient stock for product {$productId}";
                continue;
            }

            $unitPrice = Money::fromDecimalString($product['price']);
            $totalCents += $unitPrice->multiply($quantity)->cents;

            $normalized[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price_cents' => $unitPrice->cents,
            ];
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return ['total_cents' => $totalCents, 'items' => $normalized];
    }

    /**
     *
     * @param list<array{product_id: int, quantity: int}> $items
     * @param array<int, int> $currentStock indexado por product_id
     *
     * @throws ValidationException
     */
    public function assertStockAvailable(array $items, array $currentStock): void
    {
        $errors = [];

        foreach ($items as $index => $item) {
            $productId = $item['product_id'];
            $available = $currentStock[$productId] ?? 0;

            if ($available < $item['quantity']) {
                $errors["items.{$index}.quantity"] = "Insufficient stock for product {$productId}";
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
