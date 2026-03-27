<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use ArrayIterator;
use Iterator;
use Patchlevel\Rango\Model\IndexInfo;
use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use PDO;

use function array_map;
use function preg_match_all;
use function strtoupper;

use const PREG_SET_ORDER;

/** @implements Operation<Iterator<IndexInfo>> */
final class ListIndexes implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        /** @phpstan-ignore-next-line */
        private readonly array $options = [],
    ) {
    }

    /** @return Iterator<IndexInfo> */
    public function execute(PDO $pdo, QueryBuilder $queryBuilder): Iterator
    {
        $sql = $queryBuilder->createListIndexes($this->database, $this->collection);
        $statement = SqlRunner::query($pdo, $sql);

        /** @var list<array{name: string, unique: bool, definition: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $indexes = array_map(
            static function (array $row): IndexInfo {
                $keys = self::extractKeysFromDefinition($row['definition']);

                return new IndexInfo($row + ['key' => $keys, 'v' => 1]);
            },
            $rows,
        );

        return new ArrayIterator($indexes);
    }

    /** @return array<string, int> */
    private static function extractKeysFromDefinition(string $definition): array
    {
        // Extract fields with direction from index definition like:
        // CREATE UNIQUE INDEX custom_idx ON test.items USING btree (((data -> 'name'::text)))
        // Note: PostgreSQL uses spaces around ->, adds ::text cast, and multiple parentheses
        $keys = [];

        // Match: data -> 'fieldname' or data ->> 'fieldname' with optional ::text and ASC/DESC
        if (preg_match_all("/data\s*->>?\s*'([^']+)'(?:::text)?\s*\)* \s*(ASC|DESC)?/ix", $definition, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $field = $match[1];
                $direction = isset($match[2]) && strtoupper($match[2]) === 'DESC' ? -1 : 1;
                $keys[$field] = $direction;
            }
        }

        return $keys;
    }
}
