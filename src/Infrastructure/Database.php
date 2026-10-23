<?php

declare(strict_types=1);

namespace App\Infrastructure;

use mysqli;

final class Database
{
    public function __construct(private readonly mysqli $connection)
    {
    }

    public static function connectFromEnv(): self
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $conn = new mysqli(
            getenv('DB_HOST') ?: 'mysql',
            getenv('DB_USER') ?: 'orders',
            getenv('DB_PASS') ?: 'orders',
            getenv('DB_NAME') ?: 'orders',
            (int) (getenv('DB_PORT') ?: 3306),
        );
        $conn->set_charset('utf8mb4');

        return new self($conn);
    }

    public function connection(): mysqli
    {
        return $this->connection;
    }
}
