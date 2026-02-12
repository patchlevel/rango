<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\Result;
use PDO;

final class Delete implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly array $filter,
        private readonly bool $multi = false,
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): Result
    {
        $sql = $queryBuilder->createDelete($this->database, $this->collection, $this->filter, $this->multi);
        $pdo->exec($sql);

        return new Result();
    }
}
