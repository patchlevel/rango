<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use PDO;

/** @implements Operation<int> */
final class Count implements Operation
{
    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly array $filter = [],
        /** @phpstan-ignore-next-line */
        private readonly array $options = [],
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): int
    {
        $sql = $queryBuilder->createCount($this->database, $this->collection, $this->filter);
        $statement = $pdo->query($sql);

        if ($statement === false) {
            return 0;
        }

        return (int)$statement->fetchColumn();
    }
}
