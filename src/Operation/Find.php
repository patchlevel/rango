<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\Result;
use PDO;

final class Find implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly array $filter,
        private readonly array $options = [],
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): Result
    {
        $sql = $queryBuilder->createSelect($this->database, $this->collection, $this->filter, $this->options);
        $statement = $pdo->query($sql);

        if ($statement === false) {
            return new Result([]);
        }

        return new Result($statement);
    }
}
