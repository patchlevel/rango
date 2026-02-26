<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use function count;

final readonly class InsertManyResult
{
    /** @param array<int, mixed> $insertedIds */
    public function __construct(
        private array $insertedIds,
    ) {
    }

    public function getInsertedCount(): int
    {
        return count($this->insertedIds);
    }

    /** @return array<int, mixed> */
    public function getInsertedIds(): array
    {
        return $this->insertedIds;
    }

    public function isAcknowledged(): bool
    {
        return true;
    }
}
