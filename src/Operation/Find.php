<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\Model\Cursor;
use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use PDO;

/** @extends CollectionOperation<Cursor> */
final class Find extends CollectionOperation
{
    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $database,
        string $collection,
        private readonly array $filter,
        private readonly array $options = [],
    ) {
        parent::__construct($database, $collection);
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): Cursor
    {
        $sql = $queryBuilder->createSelect($this->database, $this->collection, $this->filter, $this->options);
        $statement = SqlRunner::query($pdo, $sql);

        return new Cursor($statement);
    }
}
