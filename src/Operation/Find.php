<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\Cursor;
use Patchlevel\Rango\QueryBuilder;
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
        $statement = $pdo->query($sql);

        if ($statement === false) {
            return new Cursor([]);
        }

        return new Cursor($statement);
    }
}
