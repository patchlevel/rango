<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use PDO;

/** @implements Operation<bool> */
final class RenameCollection implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        private readonly string $databaseName,
        private readonly string $oldName,
        private readonly string $newName,
        /** @phpstan-ignore-next-line */
        private readonly array $options = [],
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): bool
    {
        $sql = $queryBuilder->createRenameCollection($this->databaseName, $this->oldName, $this->newName);
        SqlRunner::exec($pdo, $sql);

        return true;
    }
}
