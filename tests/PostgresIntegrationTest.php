<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Tests;

use Patchlevel\Rango\Client;
use Patchlevel\Rango\Collection;
use Patchlevel\Rango\Database;
use Patchlevel\Rango\Exception\QueryException;
use Patchlevel\Rango\Exception\TransactionException;
use Patchlevel\Rango\Session;
use Patchlevel\Rango\SqlRunner;
use RuntimeException;

use function getenv;

final class PostgresIntegrationTest extends IntegrationTest
{
    private function requirePostgresUri(): string
    {
        $uri = getenv('POSTGRES_URI');

        if ($uri === false) {
            throw new RuntimeException('POSTGRES_URI is not set');
        }

        return $uri;
    }

    protected function setUp(): void
    {
        getenv('POSTGRES_URI') ?: $this->markTestSkipped('POSTGRES_URI is not set');

        $this->getDatabase()->dropCollection('items_new');

        parent::setUp();
    }

    protected function getClient(): Client
    {
        return new Client($this->requirePostgresUri());
    }

    protected function getCollection(): Collection
    {
        return $this->getDatabase()->getCollection('items');
    }

    protected function getDatabase(): Database
    {
        return $this->getClient()->getDatabase('test');
    }

    public function testWithTransactionCommitsOnSuccess(): void
    {
        $client = $this->getClient();
        $items = $client->getDatabase('test')->getCollection('with_tx_ok');
        $items->drop();

        $session = $client->startSession();
        $session->withTransaction(static function (Session $session) use ($items): void {
            $items->insertOne(['_id' => '1'], ['session' => $session]);
            $items->insertOne(['_id' => '2'], ['session' => $session]);
        });

        self::assertFalse($session->isInTransaction());
        self::assertSame(2, $items->countDocuments());
    }

    public function testWithTransactionRollsBackAndRethrowsOnError(): void
    {
        $client = $this->getClient();
        $items = $client->getDatabase('test')->getCollection('with_tx_err');
        $items->drop();
        $items->insertOne(['_id' => 'x']);

        $session = $client->startSession();

        try {
            $session->withTransaction(static function (Session $session) use ($items): void {
                $items->insertOne(['_id' => 'y'], ['session' => $session]);
                $items->insertOne(['_id' => 'x'], ['session' => $session]);
            });

            self::fail('expected the duplicate insert to bubble up');
        } catch (QueryException) {
            // expected
        }

        self::assertFalse($session->isInTransaction());
        self::assertSame(1, $items->countDocuments());
    }

    public function testStartingASecondTransactionThrows(): void
    {
        $session = $this->getClient()->startSession();
        $session->startTransaction();

        try {
            $this->expectException(TransactionException::class);
            $session->startTransaction();
        } finally {
            $session->abortTransaction();
        }
    }

    public function testAbortWithoutTransactionThrows(): void
    {
        $session = $this->getClient()->startSession();

        $this->expectException(TransactionException::class);
        $session->abortTransaction();
    }

    public function testReadConcernRaisesTheIsolationLevel(): void
    {
        $session = $this->getClient()->startSession();
        $session->startTransaction(['readConcern' => 'snapshot']);

        $level = SqlRunner::query($session->pdo(), 'SHOW transaction_isolation')->fetchColumn();

        $session->abortTransaction();

        self::assertSame('repeatable read', $level);
    }
}
