<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

abstract class HttpException extends RuntimeException
{
    abstract public function getStatusCode(): int;

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return [];
    }
}
