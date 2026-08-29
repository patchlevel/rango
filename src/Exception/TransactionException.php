<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Exception;

use RuntimeException;

// phpcs:disable SlevomatCodingStandard.Classes.SuperfluousExceptionNaming.SuperfluousSuffix
final class TransactionException extends RuntimeException implements Exception
{
    public static function alreadyInProgress(): self
    {
        return new self('A transaction is already in progress for this session');
    }

    public static function noTransactionStarted(): self
    {
        return new self('There is no transaction started for this session');
    }

    public static function sessionEnded(): self
    {
        return new self('The session has already been ended');
    }

    public static function connectionBusy(): self
    {
        return new self('The underlying PDO connection is already running a transaction');
    }
}
// phpcs:enable SlevomatCodingStandard.Classes.SuperfluousExceptionNaming.SuperfluousSuffix
