<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ValidationException extends HttpException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Validation failed');
    }

    public function getStatusCode(): int
    {
        return 422;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return ['errors' => $this->errors];
    }
}
