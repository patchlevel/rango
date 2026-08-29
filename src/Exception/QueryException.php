<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Exception;

use PDOException;
use RuntimeException;
use Throwable;

use function is_string;
use function sprintf;

// phpcs:disable SlevomatCodingStandard.Classes.SuperfluousExceptionNaming.SuperfluousSuffix
final class QueryException extends RuntimeException implements Exception
{
    public function __construct(
        string $query,
        string $error,
        int $code = 0,
        Throwable|null $previous = null,
        private readonly string|null $sqlState = null,
    ) {
        parent::__construct(
            sprintf("Query failed: %s\nError: %s", $query, $error),
            $code,
            $previous,
        );
    }

    public static function fromPdo(string $query, PDOException $e): self
    {
        $sqlState = $e->errorInfo[0] ?? null;
        if (!is_string($sqlState) || $sqlState === '') {
            $code = $e->getCode();
            $sqlState = is_string($code) && $code !== '' ? $code : null;
        }

        return new self($query, $e->getMessage(), (int)$e->getCode(), $e, $sqlState);
    }

    /** The five-character SQLSTATE returned by PostgreSQL, if available. */
    public function sqlState(): string|null
    {
        return $this->sqlState;
    }
}
// phpcs:enable SlevomatCodingStandard.Classes.SuperfluousExceptionNaming.SuperfluousSuffix
