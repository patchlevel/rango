<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use PDO;

use function array_map;
use function json_decode;

final class Distinct implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly string $fieldName,
        private readonly array $filter = [],
        private readonly array $options = [],
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): array
    {
        $sql = $queryBuilder->createDistinct($this->database, $this->collection, $this->fieldName, $this->filter);
        $statement = $pdo->query($sql);

        return array_map(
            static fn (string $item) => json_decode($item, true),
            $statement->fetchAll(PDO::FETCH_COLUMN),
        );
    }
}
