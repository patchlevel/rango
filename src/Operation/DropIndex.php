<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use PDO;

/** @implements Operation<bool> */
final class DropIndex implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly string $name,
        /** @phpstan-ignore-next-line */
        private readonly array $options = [],
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): bool
    {
        $sql = $queryBuilder->createDropIndex($this->database, $this->name);
        SqlRunner::exec($pdo, $sql);

        return true;
    }
}
