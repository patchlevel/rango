<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use Patchlevel\Rango\Query\AggregationBuilder;
use Patchlevel\Rango\Query\FilterBuilder;
use Patchlevel\Rango\Query\ProjectionBuilder;
use Patchlevel\Rango\Query\UpdateBuilder;
use Patchlevel\Rango\Sql\Identifier;
use PDO;
use RuntimeException;

use function array_keys;
use function explode;
use function implode;
use function json_encode;
use function sprintf;
use function str_contains;
use function str_replace;

/** @internal */
final readonly class QueryBuilder
{
    private FilterBuilder $filterBuilder;
    private ProjectionBuilder $projectionBuilder;
    private UpdateBuilder $updateBuilder;
    private AggregationBuilder $aggregationBuilder;

    public function __construct(
        private PDO $pdo,
    ) {
        $this->filterBuilder = new FilterBuilder($pdo);
        $this->projectionBuilder = new ProjectionBuilder($pdo);
        $this->updateBuilder = new UpdateBuilder($pdo);
        $this->aggregationBuilder = new AggregationBuilder($pdo, $this->filterBuilder, $this->projectionBuilder);
    }

    /** @param array<string, mixed> $document */
    public function createInsert(string $database, string $collection, array $document): string
    {
        $table = Identifier::quote($database) . '.' . Identifier::quote($collection);

        return sprintf(
            'INSERT INTO %s (data) VALUES (%s)',
            $table,
            $this->pdo->quote(json_encode($document)),
        );
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function createSelect(string $database, string $collection, array $filter = [], array $options = []): string
    {
        $table = Identifier::quote($database) . '.' . Identifier::quote($collection);
        $where = $this->filterBuilder->buildWhere($filter);

        $column = $this->projectionBuilder->buildProjectionColumn($options['projection'] ?? [], 'data');

        $sql = sprintf('SELECT %s FROM %s', $column, $table);

        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        if (isset($options['sort'])) {
            $sortParts = [];
            foreach ($options['sort'] as $field => $direction) {
                $dir = $direction === 1 || $direction === 'asc' ? 'ASC' : 'DESC';
                if ($field === '_id') {
                    $sortParts[] = sprintf("data->>'_id' %s", $dir);
                } elseif (str_contains($field, '.')) {
                    $parts = explode('.', $field);
                    $expression = 'data';
                    foreach ($parts as $part) {
                        $expression = sprintf('%s->%s', $expression, $this->pdo->quote($part));
                    }

                    $sortParts[] = sprintf('%s %s', $expression, $dir);
                } else {
                    $sortParts[] = sprintf('data->%s %s', $this->pdo->quote($field), $dir);
                }
            }

            if (!empty($sortParts)) {
                $sql .= ' ORDER BY ' . implode(', ', $sortParts);
            }
        }

        if (isset($options['limit'])) {
            $sql .= ' LIMIT ' . (int)$options['limit'];
        }

        if (isset($options['skip'])) {
            $sql .= ' OFFSET ' . (int)$options['skip'];
        }

        return $sql;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     * @param array<string, mixed> $options
     */
    public function createUpdate(string $database, string $collection, array $filter, array $update, array $options = [], bool $multi = false): string
    {
        $table = Identifier::quote($database) . '.' . Identifier::quote($collection);
        $where = $this->filterBuilder->buildWhere($filter);
        $updatePlan = $this->updateBuilder->buildUpdateExpression($update);
        $dataExpression = $updatePlan['expression'];

        if (isset($options['upsert']) && $options['upsert']) {
            if (!isset($filter['_id'])) {
                throw new RuntimeException('Upsert currently requires _id in filter');
            }

            $insertData = $this->updateBuilder->buildUpsertDocument($filter, $updatePlan['setData'], $updatePlan['setOnInsertData']);

            return sprintf(
                'INSERT INTO %s AS t (data) VALUES (%s) ON CONFLICT ((data->>\'_id\')) DO UPDATE SET data = %s',
                $table,
                $this->pdo->quote(json_encode($insertData)),
                str_replace('data', 't.data', $dataExpression),
            );
        }

        $sql = sprintf('UPDATE %s SET data = %s', $table, $dataExpression);

        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        return $sql;
    }

    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $replacement
     * @param array<string, mixed> $options
     */
    public function createReplace(string $database, string $collection, array $filter, array $replacement, array $options = []): string
    {
        $table = Identifier::quote($database) . '.' . Identifier::quote($collection);
        $where = $this->filterBuilder->buildWhere($filter);

        if (isset($options['upsert']) && $options['upsert']) {
            if (!isset($filter['_id'])) {
                throw new RuntimeException('Upsert currently requires _id in filter');
            }

            return sprintf(
                'INSERT INTO %s (data) VALUES (%s) ON CONFLICT ((data->>\'_id\')) DO UPDATE SET data = EXCLUDED.data',
                $table,
                $this->pdo->quote(json_encode($replacement)),
            );
        }

        $sql = sprintf('UPDATE %s SET data = %s', $table, $this->pdo->quote(json_encode($replacement)));

        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        return $sql;
    }

    /** @param array<string, mixed> $filter */
    public function createDelete(string $database, string $collection, array $filter, bool $multi = false): string
    {
        $table = Identifier::quote($database) . '.' . Identifier::quote($collection);
        $where = $this->filterBuilder->buildWhere($filter);

        $sql = sprintf('DELETE FROM %s', $table);

        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        return $sql;
    }

    /** @param array<string, mixed> $filter */
    public function createCount(string $database, string $collection, array $filter = []): string
    {
        $table = Identifier::quote($database) . '.' . Identifier::quote($collection);
        $where = $this->filterBuilder->buildWhere($filter);

        $sql = sprintf('SELECT COUNT(*) FROM %s', $table);

        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        return $sql;
    }

    /** @param array<string, mixed> $filter */
    public function createDistinct(string $database, string $collection, string $fieldName, array $filter = []): string
    {
        $table = Identifier::quote($database) . '.' . Identifier::quote($collection);
        $where = $this->filterBuilder->buildWhere($filter);
        $fieldExpression = $fieldName === '_id' ? "data->'_id'" : sprintf('data->%s', $this->pdo->quote($fieldName));

        $sql = sprintf('SELECT DISTINCT %s FROM %s', $fieldExpression, $table);

        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        return $sql;
    }

    /**
     * @param list<array<string, mixed>> $pipeline
     * @param array<string, mixed>       $options
     */
    public function createAggregate(string $database, string $collection, array $pipeline, array $options = []): string
    {
        return $this->aggregationBuilder->createAggregate($database, $collection, $pipeline, $options);
    }

    /**
     * @param array<string, int>   $key
     * @param array<string, mixed> $options
     */
    public function createCreateIndex(string $database, string $collection, array $key, array $options): string
    {
        $table = Identifier::quote($database) . '.' . Identifier::quote($collection);
        $name = $options['name'] ?? null;

        if ($name === null) {
            $name = $database . '_' . $collection . '_' . implode('_', array_keys($key)) . '_idx';
        }

        $unique = isset($options['unique']) && $options['unique'] ? 'UNIQUE' : '';

        $parts = [];
        foreach ($key as $field => $direction) {
            $parts[] = sprintf('(data->%s)', $this->pdo->quote($field));
        }

        return sprintf(
            'CREATE %s INDEX IF NOT EXISTS %s ON %s (%s)',
            $unique,
            Identifier::quote($name),
            $table,
            implode(', ', $parts),
        );
    }

    public function createDropIndex(string $database, string $name): string
    {
        return sprintf(
            'DROP INDEX IF EXISTS %s.%s',
            Identifier::quote($database),
            Identifier::quote($name),
        );
    }

    public function createListIndexes(string $database, string $collection): string
    {
        return sprintf(
            'SELECT indexname as name FROM pg_indexes WHERE schemaname = %s AND tablename = %s',
            $this->pdo->quote($database),
            $this->pdo->quote($collection),
        );
    }

    public function createListDatabases(): string
    {
        return "SELECT schema_name as name FROM information_schema.schemata WHERE schema_name NOT IN ('information_schema', 'pg_catalog')";
    }

    public function createListCollections(string $database): string
    {
        return sprintf(
            'SELECT table_name as name FROM information_schema.tables WHERE table_schema = %s',
            $this->pdo->quote($database),
        );
    }

    public function createRenameCollection(string $database, string $oldName, string $newName): string
    {
        return sprintf(
            'ALTER TABLE %s.%s RENAME TO %s',
            Identifier::quote($database),
            Identifier::quote($oldName),
            Identifier::quote($newName),
        );
    }
}
