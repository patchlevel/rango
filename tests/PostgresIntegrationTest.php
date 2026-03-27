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
}
