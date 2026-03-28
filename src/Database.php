<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use Iterator;
use Patchlevel\Rango\Model\CollectionInfo;

final class Database
{
    public function __construct(
        private readonly Client $client,
        private readonly string $databaseName,
    ) {
    }

    /** @return Collection<array<string, mixed>> */
    public function getCollection(string $collectionName): Collection
    {
        return new Collection($this->client, $this->databaseName, $collectionName);
    }

    /** @return Collection<array<string, mixed>> */
    public function selectCollection(string $collectionName): Collection
    {
        return $this->getCollection($collectionName);
    }

    /** @return Iterator<CollectionInfo> */
    public function listCollections(): Iterator
    {
        return $this->client->listCollections($this->databaseName);
    }

    public function renameCollection(string $oldName, string $newName): void
    {
        $this->client->renameCollection($this->databaseName, $oldName, $newName);
    }

    /** @param array<string, mixed> $options */
    public function dropCollection(string $collectionName, array $options = []): void
    {
        $this->client->getCollection($this->databaseName, $collectionName)->drop();
    }

    public function drop(): void
    {
        $this->client->dropDatabase($this->databaseName);
    }

    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }
}
