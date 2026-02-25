<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\DeleteResult;
use Patchlevel\Rango\QueryBuilder;
use PDO;

/** @implements Operation<DeleteResult> */
final class Delete implements Operation
{
    /** @param array<string, mixed> $filter */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly array $filter,
        private readonly bool $multi = false,
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): DeleteResult
    {
        $sql = $queryBuilder->createDelete($this->database, $this->collection, $this->filter, $this->multi);
        $rowCount = $pdo->exec($sql);

        return new DeleteResult($rowCount === false ? 0 : $rowCount);
    }
}
