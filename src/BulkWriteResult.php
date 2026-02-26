<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

final readonly class BulkWriteResult
{
    /**
     * @param array<int, mixed> $insertedIds
     * @param array<int, mixed> $upsertedIds
     */
    public function __construct(
        private int $insertedCount,
        private int $matchedCount,
        private int $modifiedCount,
        private int $deletedCount,
        private int $upsertedCount,
        private array $insertedIds,
        private array $upsertedIds,
    ) {
    }

    public function getInsertedCount(): int
    {
        return $this->insertedCount;
    }

    public function getMatchedCount(): int
    {
        return $this->matchedCount;
    }

    public function getModifiedCount(): int
    {
        return $this->modifiedCount;
    }

    public function getDeletedCount(): int
    {
        return $this->deletedCount;
    }

    public function getUpsertedCount(): int
    {
        return $this->upsertedCount;
    }

    /** @return array<int, mixed> */
    public function getInsertedIds(): array
    {
        return $this->insertedIds;
    }

    /** @return array<int, mixed> */
    public function getUpsertedIds(): array
    {
        return $this->upsertedIds;
    }

    public function isAcknowledged(): bool
    {
        return true;
    }
}
