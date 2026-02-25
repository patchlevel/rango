<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

// phpcs:disable SlevomatCodingStandard.Classes.SuperfluousExceptionNaming.SuperfluousSuffix
final class QueryException extends RuntimeException implements Exception
{
    public function __construct(string $query, string $error, int $code = 0, Throwable|null $previous = null)
    {
        parent::__construct(
            sprintf("Query failed: %s\nError: %s", $query, $error),
            $code,
            $previous,
        );
    }
}
// phpcs:enable SlevomatCodingStandard.Classes.SuperfluousExceptionNaming.SuperfluousSuffix
