<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use Patchlevel\Rango\Exception\QueryException;
use Patchlevel\Rango\Exception\TransactionException;
use PDO;
use PDOException;
use Throwable;

use function in_array;
use function is_array;
use function is_string;

/**
 * A session groups a series of operations into a single ACID transaction.
 *
 * Because a {@see Client} owns exactly one PDO connection, every operation issued
 * while a transaction is active automatically takes part in it. Pass the session
 * as the `session` option to keep the code drop-in compatible with `mongodb/mongodb`.
 */
final class Session
{
    private const STATE_NONE = 'none';
    private const STATE_IN_PROGRESS = 'in_progress';
    private const STATE_COMMITTED = 'committed';
    private const STATE_ABORTED = 'aborted';

    /** SQLSTATEs that are safe to retry: serialization_failure and deadlock_detected. */
    private const RETRYABLE_SQL_STATES = ['40001', '40P01'];

    private const MAX_RETRIES = 3;

    private string $transactionState = self::STATE_NONE;

    private bool $ended = false;

    /** @internal Use {@see Client::startSession()} instead. */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $options */
    public function startTransaction(array $options = []): void
    {
        if ($this->ended) {
            throw TransactionException::sessionEnded();
        }

        if ($this->transactionState === self::STATE_IN_PROGRESS) {
            throw TransactionException::alreadyInProgress();
        }

        if ($this->pdo->inTransaction()) {
            throw TransactionException::connectionBusy();
        }

        $this->pdo->beginTransaction();

        $isolationLevel = self::isolationLevelFor($options);
        if ($isolationLevel !== null) {
            SqlRunner::exec($this->pdo, 'SET TRANSACTION ISOLATION LEVEL ' . $isolationLevel);
        }

        $this->transactionState = self::STATE_IN_PROGRESS;
    }

    public function commitTransaction(): void
    {
        $this->assertInProgress();

        try {
            $this->pdo->commit();
        } catch (PDOException $e) {
            // A failed COMMIT ends the transaction on the server side. PostgreSQL
            // reports serialization failures here, so surface it like any query.
            $this->transactionState = self::STATE_ABORTED;

            throw QueryException::fromPdo('COMMIT', $e);
        }

        $this->transactionState = self::STATE_COMMITTED;
    }

    public function abortTransaction(): void
    {
        $this->assertInProgress();

        $this->rollBack();
    }

    /**
     * Run the callback inside a transaction, committing on success and rolling
     * back on failure. Transient errors (serialization failures, deadlocks) are
     * retried a few times before the exception is re-thrown.
     *
     * @param callable(self): void $callback
     * @param array<string, mixed> $options
     */
    public function withTransaction(callable $callback, array $options = []): void
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            $this->startTransaction($options);

            try {
                $callback($this);
                $this->commitTransaction();

                return;
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->rollBack();
                } else {
                    $this->transactionState = self::STATE_ABORTED;
                }

                if (self::isRetryable($e) && $attempt <= self::MAX_RETRIES) {
                    continue;
                }

                throw $e;
            }
        }
    }

    public function isInTransaction(): bool
    {
        return $this->transactionState === self::STATE_IN_PROGRESS;
    }

    /**
     * End the session. A still-open transaction is rolled back. The underlying
     * PDO connection stays open and can be reused for a new session.
     */
    public function endSession(): void
    {
        if ($this->transactionState === self::STATE_IN_PROGRESS) {
            $this->rollBack();
        }

        $this->ended = true;
    }

    /** @internal Used by the client to make sure a passed session belongs to it. */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function rollBack(): void
    {
        $this->pdo->rollBack();
        $this->transactionState = self::STATE_ABORTED;
    }

    private function assertInProgress(): void
    {
        if ($this->transactionState !== self::STATE_IN_PROGRESS) {
            throw TransactionException::noTransactionStarted();
        }
    }

    private static function isRetryable(Throwable $e): bool
    {
        return $e instanceof QueryException
            && in_array($e->sqlState(), self::RETRYABLE_SQL_STATES, true);
    }

    /** @param array<string, mixed> $options */
    private static function isolationLevelFor(array $options): string|null
    {
        $readConcern = $options['readConcern'] ?? null;

        if (is_array($readConcern)) {
            $readConcern = $readConcern['level'] ?? null;
        }

        if (!is_string($readConcern)) {
            return null;
        }

        return match ($readConcern) {
            'snapshot' => 'REPEATABLE READ',
            'linearizable' => 'SERIALIZABLE',
            default => null,
        };
    }
}
