<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\Result;
use PDO;

final class FindOneAndUpdate implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly array $filter,
        private readonly array $update,
        private readonly array $options = [],
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): Result|null
    {
        $sql = $queryBuilder->createSelect($this->database, $this->collection, $this->filter, ['limit' => 1]);
        $statement = $pdo->query($sql);
        $data = $statement->fetchColumn();

        if (!$data) {
            return null;
        }

        $sql = $queryBuilder->createUpdate($this->database, $this->collection, $this->filter, $this->update, $this->options, false);
        $pdo->exec($sql);

        return new Result([$data]);
    }
}
