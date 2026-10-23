<?php

declare(strict_types=1);

namespace App\Domain;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function canBeConfirmed(): bool
    {
        return $this === self::Pending;
    }

    public function canBeCancelled(): bool
    {
        return $this === self::Pending;
    }

    public static function fromDatabase(string $value): self
    {
        return self::from($value);
    }
}
