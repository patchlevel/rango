<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use ArrayIterator;
use Iterator;
use Patchlevel\Rango\Model\DatabaseInfo;
use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use PDO;

use function array_map;

/** @implements Operation<list<array{name: string}>> */
final class ListDatabases implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        /** @phpstan-ignore-next-line */
        private readonly array $options = [],
    ) {
    }

    /** @return Iterator<DatabaseInfo> */
    public function execute(PDO $pdo, QueryBuilder $queryBuilder): Iterator
    {
        $sql = $queryBuilder->createListDatabases();
        $statement = SqlRunner::query($pdo, $sql);

        /** @var list<array{name: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $databases = array_map(
            static fn (array $row): DatabaseInfo => new DatabaseInfo($row),
            $rows,
        );

        return new ArrayIterator($databases);
    }
}
