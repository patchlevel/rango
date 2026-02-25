<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use PDO;

use function array_map;
use function array_values;
use function is_string;
use function json_decode;

/** @implements Operation<list<mixed>> */
final class Distinct implements Operation
{
    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly string $fieldName,
        private readonly array $filter = [],
        /** @phpstan-ignore-next-line */
        private readonly array $options = [],
    ) {
    }

    /** @return list<mixed> */
    public function execute(PDO $pdo, QueryBuilder $queryBuilder): array
    {
        $sql = $queryBuilder->createDistinct($this->database, $this->collection, $this->fieldName, $this->filter);
        $statement = $pdo->query($sql);

        if ($statement === false) {
            return [];
        }

        $data = array_map(
            static fn ($item) => json_decode(is_string($item) ? $item : '', true),
            $statement->fetchAll(PDO::FETCH_COLUMN),
        );

        return array_values($data);
    }
}
