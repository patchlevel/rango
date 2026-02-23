<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Tests;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;

use function class_exists;
use function getenv;

final class MongoIntegrationTest extends IntegrationTest
{
    protected function getClient(): Client
    {
        return new Client(getenv('MONGODB_URI'));
    }

    protected function setUp(): void
    {
        if (!class_exists(Client::class)) {
            $this->markTestSkipped('mongodb/mongodb is not installed');
        }

        getenv('MONGODB_URI') ?: $this->markTestSkipped('MONGODB_URI is not set');

        parent::setUp();
    }

    protected function getCollection(): Collection
    {
        return $this->getDatabase()->selectCollection('items');
    }

    protected function getDatabase(): Database
    {
        return $this->getClient()->selectDatabase('test');
    }
}
