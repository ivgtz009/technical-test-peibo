<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        public readonly array $body,
        public readonly int $status = 200,
    ) {
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(['data' => $data], $status);
    }

    /**
     * @param list<mixed> $items
     * @param array<string, mixed> $meta
     */
    public static function paginated(array $items, array $meta, int $status = 200): self
    {
        return new self(
            ['data' => $items, 'meta' => $meta],
            $status,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function error(string $message, int $status, array $context = []): self
    {
        return new self(
            array_merge(['error' => $message], $context),
            $status,
        );
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
