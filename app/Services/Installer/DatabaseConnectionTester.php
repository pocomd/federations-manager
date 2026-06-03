<?php

declare(strict_types=1);

namespace App\Services\Installer;

use PDO;
use PDOException;

class DatabaseConnectionTester
{
    /**
     * @throws PDOException on connection failure
     */
    public function connect(
        string $host,
        int    $port,
        string $database,
        string $username,
        string $password,
    ): PDO {
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT            => 5,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /** @return string[] table names */
    public function tables(PDO $pdo): array
    {
        return $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    }
}
