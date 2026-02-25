<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use PDO;

/** @extends CollectionOperation<bool> */
final class CreateIndex extends CollectionOperation
{
    /**
     * @param array<string, int>   $key
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $database,
        string $collection,
        private readonly array $key,
        private readonly array $options = [],
    ) {
        parent::__construct($database, $collection);
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): bool
    {
        $sql = $queryBuilder->createCreateIndex($this->database, $this->collection, $this->key, $this->options);
        SqlRunner::exec($pdo, $sql);

        return true;
    }
}
