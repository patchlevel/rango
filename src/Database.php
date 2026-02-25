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

    /** @return Collection */
    public function getCollection(string $collectionName): Collection
    {
        return new Collection($this->client, $this->databaseName, $collectionName);
    }

    /** @return Collection */
    public function selectCollection(string $collectionName): Collection
    {
        return $this->getCollection($collectionName);
    }

    /** @return list<array{name: string}> */
    public function listCollections(): array
    {
        /** @var list<array{name: string}> $collections */
        $collections = $this->client->listCollections($this->databaseName);

        return $collections;
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
