<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

final class DeleteResult
{
    public function __construct(
        private readonly int $deletedCount,
    ) {
    }

    public function getDeletedCount(): int
    {
        return $this->deletedCount;
    }

    public function isAcknowledged(): bool
    {
        return true;
    }
}
