<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use Throwable;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Config::get('db.host');
        $port = Config::get('db.port');
        $name = Config::get('db.name');
        $charset = Config::get('db.charset', 'utf8mb4');
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        self::$pdo = new PDO($dsn, (string) Config::get('db.user'), (string) Config::get('db.pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 8,
        ]);
        self::$pdo->exec("SET time_zone = '+00:00'");
        return self::$pdo;
    }

    public static function connection(): ?PDO
    {
        try {
            return self::pdo();
        } catch (Throwable) {
            return null;
        }
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::execute($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Run a callback inside a transaction. Nested calls reuse the outer transaction.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $owner = !$pdo->inTransaction();
        if ($owner) {
            $pdo->beginTransaction();
        }
        try {
            $result = $callback($pdo);
            if ($owner) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($owner && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function isDuplicate(Throwable $e): bool
    {
        return $e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062;
    }

    public static function deadlockRetry(callable $callback, int $attempts = 3): mixed
    {
        $last = null;
        for ($i = 0; $i < $attempts; $i++) {
            try {
                return self::transaction($callback);
            } catch (PDOException $e) {
                $last = $e;
                $code = (int) ($e->errorInfo[1] ?? 0);
                if (!in_array($code, [1205, 1213], true)) {
                    throw $e;
                }
                usleep(120000 * ($i + 1));
            }
        }
        throw $last;
    }
}
