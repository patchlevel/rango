<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

final class InsertOneResult
{
    public function __construct(
        private readonly mixed $insertedId,
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
