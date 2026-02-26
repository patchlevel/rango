<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

final readonly class UpdateResult
{
    public function __construct(
        private int $matchedCount,
        private int $modifiedCount,
        private mixed $upsertedId = null,
    ) {
    }

    public function getMatchedCount(): int
    {
        return $this->matchedCount;
    }

    public function getModifiedCount(): int
    {
        return $this->modifiedCount;
    }

    public function getUpsertedCount(): int
    {
        return $this->upsertedId === null ? 0 : 1;
    }

    public function getUpsertedId(): mixed
    {
        return $this->upsertedId;
    }

    public function isAcknowledged(): bool
    {
        return true;
    }
}
