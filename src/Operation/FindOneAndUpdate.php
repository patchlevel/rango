<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\Cursor;
use Patchlevel\Rango\QueryBuilder;
use PDO;

use function is_string;

final class FindOneAndUpdate implements Operation
{
    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly array $filter,
        private readonly array $update,
        private readonly array $options = [],
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): Cursor|null
    {
        $sql = $queryBuilder->createSelect($this->database, $this->collection, $this->filter, ['limit' => 1]);
        $statement = $pdo->query($sql);
        if ($statement === false) {
            return null;
        }

        $data = $statement->fetchColumn();

        if (!is_string($data)) {
            return null;
        }

        $sql = $queryBuilder->createUpdate($this->database, $this->collection, $this->filter, $this->update, $this->options, false);
        $pdo->exec($sql);

        return new Cursor([$data]);
    }
}
