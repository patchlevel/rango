<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Exception;

use function sprintf;

final class DecodeException extends \RuntimeException implements Exception
{
    public function __construct(string $data, string $error, int $code = 0, \Throwable|null $previous = null)
    {
        parent::__construct(
            sprintf("Could not decode JSON: %s\nError: %s", $data, $error),
            $code,
            $previous
        );
    }
}
