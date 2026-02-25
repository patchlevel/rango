<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\DeleteResult;
use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use PDO;

/** @extends CollectionOperation<DeleteResult> */
final class Delete extends CollectionOperation
{
    /** @param array<string, mixed> $filter */
    public function __construct(
        string $database,
        string $collection,
        private readonly array $filter,
        private readonly bool $multi = false,
    ) {
        parent::__construct($database, $collection);
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): DeleteResult
    {
        $sql = $queryBuilder->createDelete($this->database, $this->collection, $this->filter, $this->multi);
        $rowCount = SqlRunner::exec($pdo, $sql);

        return new DeleteResult($rowCount);
    }
}
