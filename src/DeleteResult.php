<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

final readonly class DeleteResult
{
    public function __construct(
        private int $deletedCount,
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
