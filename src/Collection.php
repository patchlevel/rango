<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

class Collection
{
    public function __construct(
        private readonly Client $client,
        private readonly string $databaseName,
        private readonly string $collectionName,
    ) {
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function countDocuments(array $filter = [], array $options = []): int
    {
        return $this->client->execute($this->databaseName, $this->collectionName, 'count', [
            'filter' => $filter,
            'options' => $options,
        ]);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function deleteMany(array $filter, array $options = []): Result
    {
        return $this->client->execute($this->databaseName, $this->collectionName, 'deleteMany', [
            'filter' => $filter,
            'options' => $options,
        ]);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function deleteOne(array $filter, array $options = []): Result
    {
        return $this->client->execute($this->databaseName, $this->collectionName, 'deleteOne', [
            'filter' => $filter,
            'options' => $options,
        ]);
    }

    public function drop(): void
    {
        $this->client->execute($this->databaseName, $this->collectionName, 'drop', []);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function find(array $filter = [], array $options = []): Result
    {
        return $this->client->execute($this->databaseName, $this->collectionName, 'find', [
            'filter' => $filter,
            'options' => $options,
        ]);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function findOne(array $filter = [], array $options = []): array|object|null
    {
        $result = $this->client->execute($this->databaseName, $this->collectionName, 'findOne', [
            'filter' => $filter,
            'options' => $options,
        ]);

        if (!$result instanceof Result) {
            return null;
        }

        $data = $result->toArray();

        return $data[0] ?? null;
    }

    /**
     * @param list<array<string, mixed>> $documents
     * @param array<string, mixed>       $options
     */
    public function insertMany(array $documents, array $options = []): Result
    {
        return $this->client->execute($this->databaseName, $this->collectionName, 'insertMany', [
            'documents' => $documents,
            'options' => $options,
        ]);
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $options
     */
    public function insertOne(array $document, array $options = []): Result
    {
        return $this->client->execute($this->databaseName, $this->collectionName, 'insertOne', [
            'document' => $document,
            'options' => $options,
        ]);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $replacement
     * @param array<string, mixed> $options
     */
    public function replaceOne(array $filter, array $replacement, array $options = []): Result
    {
        return $this->client->execute($this->databaseName, $this->collectionName, 'replaceOne', [
            'filter' => $filter,
            'replacement' => $replacement,
            'options' => $options,
        ]);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     * @param array<string, mixed> $options
     */
    public function updateMany(array $filter, array $update, array $options = []): Result
    {
        return $this->client->execute($this->databaseName, $this->collectionName, 'updateMany', [
            'filter' => $filter,
            'update' => $update,
            'options' => $options,
        ]);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     * @param array<string, mixed> $options
     */
    public function updateOne(array $filter, array $update, array $options = []): Result
    {
        return $this->client->execute($this->databaseName, $this->collectionName, 'updateOne', [
            'filter' => $filter,
            'update' => $update,
            'options' => $options,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $pipeline
     * @param array<string, mixed>       $options
     */
    public function aggregate(array $pipeline, array $options = []): Result
    {
        return $this->client->execute($this->databaseName, $this->collectionName, 'aggregate', [
            'pipeline' => $pipeline,
            'options' => $options,
        ]);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     *
     * @return list<mixed>
     */
    public function distinct(string $fieldName, array $filter = [], array $options = []): array
    {
        return $this->client->execute($this->databaseName, $this->collectionName, 'distinct', [
            'fieldName' => $fieldName,
            'filter' => $filter,
            'options' => $options,
        ]);
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function findOneAndDelete(array $filter, array $options = []): array|object|null
    {
        $result = $this->client->execute($this->databaseName, $this->collectionName, 'findOneAndDelete', [
            'filter' => $filter,
            'options' => $options,
        ]);

        if (!$result instanceof Result) {
            return null;
        }

        $data = $result->toArray();

        return $data[0] ?? null;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $replacement
     * @param array<string, mixed> $options
     */
    public function findOneAndReplace(array $filter, array $replacement, array $options = []): array|object|null
    {
        $result = $this->client->execute($this->databaseName, $this->collectionName, 'findOneAndReplace', [
            'filter' => $filter,
            'replacement' => $replacement,
            'options' => $options,
        ]);

        if (!$result instanceof Result) {
            return null;
        }

        $data = $result->toArray();

        return $data[0] ?? null;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     * @param array<string, mixed> $options
     */
    public function findOneAndUpdate(array $filter, array $update, array $options = []): array|object|null
    {
        $result = $this->client->execute($this->databaseName, $this->collectionName, 'findOneAndUpdate', [
            'filter' => $filter,
            'update' => $update,
            'options' => $options,
        ]);

        if (!$result instanceof Result) {
            return null;
        }

        $data = $result->toArray();

        return $data[0] ?? null;
    }

    /**
     * @param array<string, int>   $key
     * @param array<string, mixed> $options
     */
    public function createIndex(array $key, array $options = []): void
    {
        $this->client->execute($this->databaseName, $this->collectionName, 'createIndex', [
            'key' => $key,
            'options' => $options,
        ]);
    }

    public function dropIndex(string $name): void
    {
        $this->client->execute($this->databaseName, $this->collectionName, 'dropIndex', [
            'name' => $name,
        ]);
    }

    /**
     * @return list<array{name: string}>
     */
    public function listIndexes(): array
    {
        return $this->client->execute($this->databaseName, $this->collectionName, 'listIndexes', []);
    }
}
