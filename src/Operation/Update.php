<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\UpdateResult;
use PDO;

/** @extends CollectionOperation<UpdateResult> */
final class Update extends CollectionOperation
{
    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $database,
        string $collection,
        private readonly array $filter,
        private readonly array $update,
        private readonly array $options = [],
        private readonly bool $multi = false,
    ) {
        parent::__construct($database, $collection);
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): UpdateResult
    {
        $upsert = $this->options['upsert'] ?? false;
        $sql = $queryBuilder->createUpdate($this->database, $this->collection, $this->filter, $this->update, $this->options, $this->multi);
        $rowCount = $pdo->exec($sql);
        $rowCount = $rowCount === false ? 0 : $rowCount;

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
