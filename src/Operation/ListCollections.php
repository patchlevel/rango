<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use ArrayIterator;
use Iterator;
use Patchlevel\Rango\Model\CollectionInfo;
use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use PDO;

use function array_map;

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

    /** @return Iterator<CollectionInfo> */
    public function execute(PDO $pdo, QueryBuilder $queryBuilder): Iterator
    {
        $sql = $queryBuilder->createListCollections($this->databaseName);
        $statement = SqlRunner::query($pdo, $sql);

        /** @var list<array{name: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $collections = array_map(
            static fn (array $row): CollectionInfo => new CollectionInfo($row),
            $rows,
        );

        return new ArrayIterator($collections);
    }
}
