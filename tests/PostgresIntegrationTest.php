<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Tests;

use Patchlevel\Rango\Client;
use Patchlevel\Rango\Collection;
use Patchlevel\Rango\Database;
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

    /**
     * Date expression operators work on ISO 8601 date strings, which is how
     * Rango stores dates in JSONB. Real MongoDB requires a BSON date here, so
     * this behaviour is verified against Postgres only.
     */
    public function testDateExpressionOperators(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'ts' => '2024-03-15T12:00:00Z', 'amount' => 10],
            ['_id' => '2', 'ts' => '2024-03-20T12:00:00Z', 'amount' => 5],
            ['_id' => '3', 'ts' => '2024-04-01T12:00:00Z', 'amount' => 7],
        ]);

        $byMonth = $this->toPlainArrays($this->collection->aggregate([
            [
                '$group' => [
                    '_id' => ['$dateToString' => ['format' => '%Y-%m', 'date' => '$ts']],
                    'revenue' => ['$sum' => '$amount'],
                ],
            ],
            ['$sort' => ['_id' => 1]],
        ]));

        self::assertSame('2024-03', $byMonth[0]['_id']);
        self::assertEquals(15, $byMonth[0]['revenue']);
        self::assertSame('2024-04', $byMonth[1]['_id']);
        self::assertEquals(7, $byMonth[1]['revenue']);

        $parts = $this->toPlainArrays($this->collection->aggregate([
            ['$match' => ['_id' => '1']],
            [
                '$project' => [
                    '_id' => 0,
                    'year' => ['$year' => '$ts'],
                    'month' => ['$month' => '$ts'],
                    'day' => ['$dayOfMonth' => '$ts'],
                ],
            ],
        ]));

        self::assertEquals(['year' => 2024, 'month' => 3, 'day' => 15], $parts[0]);
    }
}
