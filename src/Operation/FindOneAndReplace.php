<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\Cursor;
use PDO;

final class FindOneAndReplace implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly array $filter,
        private readonly array $replacement,
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

        $sql = $queryBuilder->createReplace($this->database, $this->collection, $this->filter, $this->replacement, $this->options);
        $pdo->exec($sql);

        return new Cursor([$data]);
    }
}
