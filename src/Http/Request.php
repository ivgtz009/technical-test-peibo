<?php

declare(strict_types=1);

namespace App\Http;

use App\Exceptions\ValidationException;
use JsonException;

final class Request
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $query
     */
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $body,
        public readonly array $query,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        $raw = file_get_contents('php://input') ?: '';
        $body = [];

        if ($raw !== '') {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new ValidationException(['body' => 'Invalid JSON']);
            }

            if (!is_array($decoded)) {
                throw new ValidationException(['body' => 'Body must be a JSON object']);
            }
            $body = $decoded;
        }

        /** @var array<string, string> $query */
        $query = $_GET;

        return new self(
            strtoupper($method),
            rtrim($path, '/') ?: '/',
            $body,
            $query,
        );
    }
}
