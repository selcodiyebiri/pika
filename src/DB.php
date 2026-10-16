<?php
namespace Pika;

use PDO;
use PDOStatement;

/**
 * Thin static PDO wrapper. Errors throw PDOException (ERRMODE_EXCEPTION).
 */
class DB
{
    private static ?PDO $pdo = null;

    public static function instance(): PDO
    {
        return self::$pdo ??= self::init();
    }

    public static function init(): PDO
    {
        // A unix socket (e.g. MAMP) takes precedence over host/port
        $target = MYSQL_SOCKET !== '' ? 'unix_socket=' . MYSQL_SOCKET : 'host=' . MYSQL_HOST . ';port=' . MYSQL_PORT;
        $dsn = 'mysql:' . $target . ';dbname=' . MYSQL_DB . ';charset=' . CHARSET;

        return self::$pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        ]);
    }

    public static function table(string $table): Query
    {
        return Query::table($table);
    }

    public static function query(string $query, ?array $bindings = null): PDOStatement
    {
        if ($bindings === null) {
            return self::instance()->query($query);
        }
        $stmt = self::instance()->prepare($query);
        $stmt->execute($bindings);
        return $stmt;
    }

    public static function getVar(string $query, ?array $bindings = null)
    {
        return self::query($query, $bindings)->fetchColumn();
    }

    public static function getRow(string $query, ?array $bindings = null)
    {
        return self::query($query, $bindings)->fetch();
    }

    public static function get(string $query, ?array $bindings = null): array
    {
        return self::query($query, $bindings)->fetchAll();
    }

    public static function exec(string $query, ?array $bindings = null): int
    {
        return self::query($query, $bindings)->rowCount();
    }

    public static function insert(string $query, ?array $bindings = null): string
    {
        self::query($query, $bindings);
        return self::instance()->lastInsertId();
    }

    /** Run $fn inside a transaction; rolls back and rethrows on failure. */
    public static function transaction(callable $fn)
    {
        $pdo = self::instance();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
