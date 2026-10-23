<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\OrderCalculator;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class OrderCalculatorTest extends TestCase
{
    private OrderCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new OrderCalculator();
    }

    public function testCalculatesTotalFromCurrentPrices(): void
    {
        $products = [
            1 => ['id' => 1, 'price' => '10.50', 'stock' => 5],
            2 => ['id' => 2, 'price' => '3.25', 'stock' => 10],
        ];

        $result = $this->calculator->calculate(
            [
                ['product_id' => 1, 'quantity' => 2],
                ['product_id' => 2, 'quantity' => 4],
            ],
            $products,
        );

        self::assertSame(3400, $result['total_cents']);
        self::assertCount(2, $result['items']);
        self::assertSame(1050, $result['items'][0]['unit_price_cents']);
        self::assertSame(325, $result['items'][1]['unit_price_cents']);
    }

    public function testRejectsEmptyItems(): void
    {
        $this->expectException(ValidationException::class);
        $this->calculator->calculate([], []);
    }

    public function testReportsAllOffendingFieldsAtOnce(): void
    {
        try {
            $this->calculator->calculate(
                [
                    ['product_id' => 999, 'quantity' => 1],
                    ['product_id' => 1, 'quantity' => 0],
                ],
                [1 => ['id' => 1, 'price' => '10.00', 'stock' => 5]],
            );
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getContext()['errors'];
            self::assertArrayHasKey('items.0.product_id', $errors);
            self::assertArrayHasKey('items.1.quantity', $errors);
            self::assertCount(2, $errors);
        }
    }

    public function testRejectsInsufficientStock(): void
    {
        $this->expectException(ValidationException::class);
        $this->calculator->calculate(
            [['product_id' => 1, 'quantity' => 99]],
            [1 => ['id' => 1, 'price' => '10.00', 'stock' => 1]],
        );
    }

    public function testAssertStockAvailablePassesWhenEnough(): void
    {
        $this->calculator->assertStockAvailable(
            [['product_id' => 1, 'quantity' => 3]],
            [1 => 5],
        );
        self::assertTrue(true);
    }

    public function testAssertStockAvailableFailsWhenNotEnough(): void
    {
        $this->expectException(ValidationException::class);
        $this->calculator->assertStockAvailable(
            [['product_id' => 1, 'quantity' => 5]],
            [1 => 2],
        );
    }
}
