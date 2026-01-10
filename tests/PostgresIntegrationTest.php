<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Tests;

use Patchlevel\Rango\Client;
use Patchlevel\Rango\Collection;
use Patchlevel\Rango\Database;

use function getenv;

final class PostgresIntegrationTest extends IntegrationTest
{
    protected function getClient(): Client
    {
        $uri = getenv('POSTGRES_URI') ?: 'pgsql:host=localhost;port=5432;dbname=eventstore;user=postgres;password=postgres';

        return new Client($uri);
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
