<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use PDO;

/** @implements Operation<list<array{name: string}>> */
final class ListDatabases implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        /** @phpstan-ignore-next-line */
        private readonly array $options = [],
    ) {
    }

    /** @return list<array{name: string}> */
    public function execute(PDO $pdo, QueryBuilder $queryBuilder): array
    {
        $sql = $queryBuilder->createListDatabases();
        $statement = SqlRunner::query($pdo, $sql);

        /** @var list<array{name: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }
}
