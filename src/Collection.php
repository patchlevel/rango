<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use function assert;
use function is_int;

final class Collection
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
        $count = $this->client->run(new Operation\Count($this->databaseName, $this->collectionName, $filter, $options));
        assert(is_int($count));

        return $count;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function deleteMany(array $filter, array $options = []): DeleteResult
    {
        $result = $this->client->run(new Operation\Delete($this->databaseName, $this->collectionName, $filter, true));
        assert($result instanceof DeleteResult);

        return $result;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function deleteOne(array $filter, array $options = []): DeleteResult
    {
        $result = $this->client->run(new Operation\Delete($this->databaseName, $this->collectionName, $filter, false));
        assert($result instanceof DeleteResult);

        return $result;
    }

    public function drop(): void
    {
        $this->client->run(new Operation\DropCollection($this->databaseName, $this->collectionName));
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function find(array $filter = [], array $options = []): Cursor
    {
        $cursor = $this->client->run(new Operation\Find($this->databaseName, $this->collectionName, $filter, $options));
        assert($cursor instanceof Cursor);

        return $cursor;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>|null
     */
    public function findOne(array $filter = [], array $options = []): array|null
    {
        $result = $this->client->run(new Operation\FindOne($this->databaseName, $this->collectionName, $filter, $options));
        assert($result instanceof Cursor || $result === null);

        if (!$result instanceof Cursor) {
            return null;
        }

        $data = $result->toArray();

        return $data[0] ?? null;
    }

    /**
     * @param list<array<string, mixed>> $documents
     * @param array<string, mixed>       $options
     */
    public function insertMany(array $documents, array $options = []): InsertManyResult
    {
        $result = $this->client->run(new Operation\InsertMany($this->databaseName, $this->collectionName, $documents, $options));
        assert($result instanceof InsertManyResult);

        return $result;
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $options
     */
    public function insertOne(array $document, array $options = []): InsertOneResult
    {
        $result = $this->client->run(new Operation\InsertOne($this->databaseName, $this->collectionName, $document, $options));
        assert($result instanceof InsertOneResult);

        return $result;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $replacement
     * @param array<string, mixed> $options
     */
    public function replaceOne(array $filter, array $replacement, array $options = []): UpdateResult
    {
        $result = $this->client->run(new Operation\ReplaceOne($this->databaseName, $this->collectionName, $filter, $replacement, $options));
        assert($result instanceof UpdateResult);

        return $result;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     * @param array<string, mixed> $options
     */
    public function updateMany(array $filter, array $update, array $options = []): UpdateResult
    {
        $result = $this->client->run(new Operation\Update($this->databaseName, $this->collectionName, $filter, $update, $options, true));
        assert($result instanceof UpdateResult);

        return $result;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     * @param array<string, mixed> $options
     */
    public function updateOne(array $filter, array $update, array $options = []): UpdateResult
    {
        $result = $this->client->run(new Operation\Update($this->databaseName, $this->collectionName, $filter, $update, $options, false));
        assert($result instanceof UpdateResult);

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $pipeline
     * @param array<string, mixed>       $options
     */
    public function aggregate(array $pipeline, array $options = []): Cursor
    {
        $cursor = $this->client->run(new Operation\Aggregate($this->databaseName, $this->collectionName, $pipeline, $options));
        assert($cursor instanceof Cursor);

        return $cursor;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     *
     * @return list<mixed>
     */
    public function distinct(string $fieldName, array $filter = [], array $options = []): array
    {
        /** @var list<mixed> $result */
        $result = $this->client->run(new Operation\Distinct($this->databaseName, $this->collectionName, $fieldName, $filter, $options));

        return $result;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>|null
     */
    public function findOneAndDelete(array $filter, array $options = []): array|null
    {
        $result = $this->client->run(new Operation\FindOneAndDelete($this->databaseName, $this->collectionName, $filter, $options));
        assert($result instanceof Cursor || $result === null);

        if (!$result instanceof Cursor) {
            return null;
        }

        $data = $result->toArray();

        return $data[0] ?? null;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $replacement
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>|null
     */
    public function findOneAndReplace(array $filter, array $replacement, array $options = []): array|null
    {
        $result = $this->client->run(new Operation\FindOneAndReplace($this->databaseName, $this->collectionName, $filter, $replacement, $options));
        assert($result instanceof Cursor || $result === null);

        if (!$result instanceof Cursor) {
            return null;
        }

        $data = $result->toArray();

        return $data[0] ?? null;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>|null
     */
    public function findOneAndUpdate(array $filter, array $update, array $options = []): array|null
    {
        $result = $this->client->run(new Operation\FindOneAndUpdate($this->databaseName, $this->collectionName, $filter, $update, $options));
        assert($result instanceof Cursor || $result === null);

        if (!$result instanceof Cursor) {
            return null;
        }

        $data = $result->toArray();

        return $data[0] ?? null;
    }

    /**
     * @param list<array<string, list<array<string, mixed>>>> $operations
     * @param array<string, mixed>                            $options
     */
    public function bulkWrite(array $operations, array $options = []): BulkWriteResult
    {
        $result = $this->client->run(new Operation\BulkWrite($this->databaseName, $this->collectionName, $operations, $options));
        assert($result instanceof BulkWriteResult);

        return $result;
    }

    /**
     * @param array<string, int>   $key
     * @param array<string, mixed> $options
     */
    public function createIndex(array $key, array $options = []): void
    {
        $this->client->run(new Operation\CreateIndex($this->databaseName, $this->collectionName, $key, $options));
    }

    public function dropIndex(string $name): void
    {
        $this->client->run(new Operation\DropIndex($this->databaseName, $this->collectionName, $name));
    }

    /** @return list<array{name: string}> */
    public function listIndexes(): array
    {
        /** @var list<array{name: string}> $indexes */
        $indexes = $this->client->run(new Operation\ListIndexes($this->databaseName, $this->collectionName));

        return $indexes;
    }
}
