<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use PDO;

final class ListIndexes implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        /** @phpstan-ignore-next-line */
        private readonly array $options = [],
    ) {
    }

    /** @return list<array{name: string}> */
    public function execute(PDO $pdo, QueryBuilder $queryBuilder): array
    {
        $sql = $queryBuilder->createListIndexes($this->database, $this->collection);
        $statement = $pdo->query($sql);

        if ($statement === false) {
            return [];
        }

        /** @var list<array{name: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }
}
