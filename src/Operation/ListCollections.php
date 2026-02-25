<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use PDO;

/** @implements Operation<list<array{name: string}>> */
final class ListCollections implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        private readonly string $databaseName,
        /** @phpstan-ignore-next-line */
        private readonly array $options = [],
    ) {
    }

    /** @return list<array{name: string}> */
    public function execute(PDO $pdo, QueryBuilder $queryBuilder): array
    {
        $sql = $queryBuilder->createListCollections($this->databaseName);
        $statement = SqlRunner::query($pdo, $sql);

        /** @var list<array{name: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }
}
