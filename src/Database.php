<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

final class Database
{
    public function __construct(
        private readonly Client $client,
        private readonly string $databaseName,
    ) {
    }

    public function getCollection(string $collectionName): Collection
    {
        return new Collection($this->client, $this->databaseName, $collectionName);
    }

    public function selectCollection(string $collectionName): Collection
    {
        return $this->getCollection($collectionName);
    }

    /** @return list<array{name: string}> */
    public function listCollections(): array
    {
        return $this->client->listCollections($this->databaseName);
    }

    public function renameCollection(string $oldName, string $newName): void
    {
        $this->client->renameCollection($this->databaseName, $oldName, $newName);
    }

    public function drop(): void
    {
        $this->client->dropDatabase($this->databaseName);
    }
}
