<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\Cursor;
use Patchlevel\Rango\QueryBuilder;
use PDO;

final class FindOneAndDelete implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly array $filter,
        private readonly array $options = [],
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): Cursor|null
    {
        $sql = $queryBuilder->createSelect($this->database, $this->collection, $this->filter, ['limit' => 1]);
        $statement = $pdo->query($sql);
        $data = $statement->fetchColumn();

        if (!$data) {
            return null;
        }

        $sql = $queryBuilder->createDelete($this->database, $this->collection, $this->filter, false);
        $pdo->exec($sql);

        return new Cursor([$data]);
    }
}
