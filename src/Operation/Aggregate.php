<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\Result;
use PDO;

final class Aggregate implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly array $pipeline,
        private readonly array $options = [],
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): Result
    {
        $sql = $queryBuilder->createAggregate($this->database, $this->collection, $this->pipeline, $this->options);
        $statement = $pdo->query($sql);

        if ($statement === false) {
            return new Result([]);
        }

        return new Result($statement);
    }
}
