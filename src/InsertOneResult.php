<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

final readonly class InsertOneResult
{
    public function __construct(
        private mixed $insertedId,
    ) {
    }

    public function getInsertedCount(): int
    {
        return 1;
    }

    public function getInsertedId(): mixed
    {
        return $this->insertedId;
    }

    public function isAcknowledged(): bool
    {
        return true;
    }
}
