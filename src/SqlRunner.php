<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use Patchlevel\Rango\Exception\QueryException;
use PDO;
use PDOException;
use PDOStatement;

final class SqlRunner
{
    private function __construct()
    {
    }

    public static function exec(PDO $pdo, string $sql): int
    {
        try {
            $rowCount = $pdo->exec($sql);
        } catch (PDOException $e) {
            throw QueryException::fromPdo($sql, $e);
        }

        if ($rowCount === false) {
            throw new QueryException($sql, 'Unknown error');
        }

        return $rowCount;
    }

    public static function query(PDO $pdo, string $sql): PDOStatement
    {
        try {
            $statement = $pdo->query($sql);
        } catch (PDOException $e) {
            throw QueryException::fromPdo($sql, $e);
        }

        if ($statement === false) {
            throw new QueryException($sql, 'Unknown error');
        }

        return $statement;
    }
}
