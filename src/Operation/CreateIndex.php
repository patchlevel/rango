<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use PDO;

final class CreateIndex implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly array $key,
        private readonly array $options = [],
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): bool
    {
        $sql = $queryBuilder->createCreateIndex($this->database, $this->collection, $this->key, $this->options);
        $pdo->exec($sql);

        return true;
    }
}
