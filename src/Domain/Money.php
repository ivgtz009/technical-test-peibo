<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

final class Money
{
    private function __construct(public readonly int $cents)
    {
    }

    public static function fromCents(int $cents): self
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('Money cannot be negative');
        }

        return new self($cents);
    }

    public static function fromDecimalString(string $amount): self
    {
        if (preg_match('/^\d+(\.\d{1,2})?$/', $amount) !== 1) {
            throw new InvalidArgumentException("Invalid money amount: {$amount}");
        }

        [$whole, $fraction] = array_pad(explode('.', $amount), 2, '0');
        $cents = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');

        return new self($cents);
    }

    public function multiply(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Quantity cannot be negative');
        }

        return new self($this->cents * $quantity);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function toDecimalString(): string
    {
        return number_format($this->cents / 100, 2, '.', '');
    }
}
