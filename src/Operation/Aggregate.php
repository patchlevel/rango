<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\Cursor;
use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use PDO;

/** @extends CollectionOperation<Cursor> */
final class Aggregate extends CollectionOperation
{
    /**
     * @param list<array<string, mixed>> $pipeline
     * @param array<string, mixed>       $options
     */
    public function __construct(
        string $database,
        string $collection,
        private readonly array $pipeline,
        private readonly array $options = [],
    ) {
        parent::__construct($database, $collection);
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): Cursor
    {
        $sql = $queryBuilder->createAggregate($this->database, $this->collection, $this->pipeline, $this->options);
        $statement = SqlRunner::query($pdo, $sql);

        return new Cursor($statement);
    }
}
