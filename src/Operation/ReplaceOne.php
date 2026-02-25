<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use Patchlevel\Rango\UpdateResult;
use PDO;

/** @extends CollectionOperation<UpdateResult> */
final class ReplaceOne extends CollectionOperation
{
    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $replacement
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $database,
        string $collection,
        private readonly array $filter,
        private readonly array $replacement,
        private readonly array $options = [],
    ) {
        parent::__construct($database, $collection);
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): UpdateResult
    {
        $upsert = $this->options['upsert'] ?? false;
        $sql = $queryBuilder->createReplace($this->database, $this->collection, $this->filter, $this->replacement, $this->options);
        $rowCount = SqlRunner::exec($pdo, $sql);

        if ($upsert && $rowCount === 1) {
            $matchedCount = 0;
            $upsertedId = $this->filter['_id'] ?? null;
        } else {
            $matchedCount = $rowCount;
            $upsertedId = null;
        }

        return new UpdateResult($matchedCount, $rowCount, $upsertedId);
    }
}
