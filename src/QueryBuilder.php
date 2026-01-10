<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use PDO;
use RuntimeException;

use function array_keys;
use function array_map;
use function array_merge;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function json_encode;
use function ltrim;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;

/** @internal */
final class QueryBuilder
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function createInsert(string $database, string $collection, array $document): string
    {
        $table = $this->quoteIdentifier($database) . '.' . $this->quoteIdentifier($collection);

        return sprintf(
            'INSERT INTO %s (data) VALUES (%s)',
            $table,
            $this->pdo->quote(json_encode($document)),
        );
    }

    public function createSelect(string $database, string $collection, array $filter = [], array $options = []): string
    {
        $table = $this->quoteIdentifier($database) . '.' . $this->quoteIdentifier($collection);
        $where = $this->buildWhere($filter);

        $column = 'data';

        if (isset($options['projection']) && !empty($options['projection'])) {
            $projection = $options['projection'];
            $include = [];
            $exclude = [];

            foreach ($projection as $field => $value) {
                if ($value) {
                    $include[] = $field;
                } else {
                    $exclude[] = $field;
                }
            }

            if (!empty($include)) {
                // MongoDB doesn't allow mixing inclusion and exclusion except for _id
                // We simplify: if there are inclusions, we use jsonb_build_object
                $fields = [];
                foreach ($include as $field) {
                    $fields[] = $this->pdo->quote($field);
                    $fields[] = sprintf('data->%s', $this->pdo->quote($field));
                }

                if (!in_array('_id', $include, true) && (!isset($projection['_id']) || $projection['_id'])) {
                     // if _id is not explicitly excluded and there are other inclusions, MongoDB usually includes it
                     // but here we follow the "include" list strictly for simplicity or add _id if not excluded
                     $fields[] = $this->pdo->quote('_id');
                     $fields[] = "data->'_id'";
                }

                $column = sprintf('jsonb_build_object(%s)', implode(', ', $fields));
            } elseif (!empty($exclude)) {
                $column = 'data';
                foreach ($exclude as $field) {
                    $column = sprintf('%s - %s', $column, $this->pdo->quote($field));
                }
            }
        }

        $sql = sprintf('SELECT %s FROM %s', $column, $table);

        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        if (isset($options['sort'])) {
            $sortParts = [];
            foreach ($options['sort'] as $field => $direction) {
                $dir = $direction === 1 || $direction === 'asc' ? 'ASC' : 'DESC';
                if ($field === '_id') {
                    $sortParts[] = sprintf("data->>\'_id\' %s", $dir);
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

    public function createUpdate(string $database, string $collection, array $filter, array $update, array $options = [], bool $multi = false): string
    {
        $table = $this->quoteIdentifier($database) . '.' . $this->quoteIdentifier($collection);
        $where = $this->buildWhere($filter);

        $setData = $update['$set'] ?? null;
        $incData = $update['$inc'] ?? null;
        $unsetData = $update['$unset'] ?? null;
        $pushData = $update['$push'] ?? null;
        $pullData = $update['$pull'] ?? null;
        $renameData = $update['$rename'] ?? null;
        $minData = $update['$min'] ?? null;
        $maxData = $update['$max'] ?? null;

        $updateParts = [];

        if ($setData) {
            $updateParts[] = sprintf('data || %s', $this->pdo->quote(json_encode($setData)));
        }

        if ($incData) {
            foreach ($incData as $field => $value) {
                $updateParts[] = sprintf(
                    'jsonb_set(data, %s, (COALESCE(data->>%s, \'0\')::numeric + %s)::text::jsonb)',
                    $this->pdo->quote('{' . $field . '}'),
                    $this->pdo->quote($field),
                    (float)$value,
                );
            }
        }

        if ($unsetData) {
            foreach ($unsetData as $field => $value) {
                $updateParts[] = sprintf('data - %s', $this->pdo->quote($field));
            }
        }

        if ($renameData) {
            foreach ($renameData as $oldField => $newField) {
                $updateParts[] = sprintf(
                    'jsonb_set(data - %s, %s, data->%s)',
                    $this->pdo->quote($oldField),
                    $this->pdo->quote('{' . $newField . '}'),
                    $this->pdo->quote($oldField),
                );
            }
        }

        if ($minData) {
            foreach ($minData as $field => $value) {
                $updateParts[] = sprintf(
                    'jsonb_set(data, %s, LEAST(data->%s, %s::jsonb))',
                    $this->pdo->quote('{' . $field . '}'),
                    $this->pdo->quote($field),
                    $this->pdo->quote(json_encode($value)),
                );
            }
        }

        if ($maxData) {
            foreach ($maxData as $field => $value) {
                $updateParts[] = sprintf(
                    'jsonb_set(data, %s, GREATEST(data->%s, %s::jsonb))',
                    $this->pdo->quote('{' . $field . '}'),
                    $this->pdo->quote($field),
                    $this->pdo->quote(json_encode($value)),
                );
            }
        }

        if ($pushData) {
            foreach ($pushData as $field => $value) {
                $updateParts[] = sprintf(
                    'jsonb_set(data, %s, COALESCE(data->%s, \'[]\'::jsonb) || %s)',
                    $this->pdo->quote('{' . $field . '}'),
                    $this->pdo->quote($field),
                    $this->pdo->quote(json_encode($value)),
                );
            }
        }

        if ($pullData) {
            foreach ($pullData as $field => $value) {
                // This is a bit complex in Postgres JSONB.
                // We use a subquery/expression to filter the array.
                $updateParts[] = sprintf(
                    'jsonb_set(data, %s, COALESCE((SELECT jsonb_agg(x) FROM jsonb_array_elements(data->%s) x WHERE x != %s), \'[]\'::jsonb))',
                    $this->pdo->quote('{' . $field . '}'),
                    $this->pdo->quote($field),
                    $this->pdo->quote(json_encode($value)),
                );
            }
        }

        if (empty($updateParts)) {
            throw new RuntimeException('No update operators found');
        }

        // Apply transformations sequentially
        $dataExpression = 'data';
        foreach ($updateParts as $part) {
            if (str_starts_with($part, 'data ')) {
                $dataExpression = str_replace('data ', $dataExpression . ' ', $part);
            } else {
                $dataExpression = str_replace('data,', $dataExpression . ',', $part);
            }
        }

        if (isset($options['upsert']) && $options['upsert']) {
            if (!isset($filter['_id'])) {
                throw new RuntimeException('Upsert currently requires _id in filter');
            }

            $insertData = array_merge($filter, $setData ?? []);

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

    public function createReplace(string $database, string $collection, array $filter, array $replacement, array $options = []): string
    {
        $table = $this->quoteIdentifier($database) . '.' . $this->quoteIdentifier($collection);
        $where = $this->buildWhere($filter);

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

    public function createDelete(string $database, string $collection, array $filter, bool $multi = false): string
    {
        $table = $this->quoteIdentifier($database) . '.' . $this->quoteIdentifier($collection);
        $where = $this->buildWhere($filter);

        $sql = sprintf('DELETE FROM %s', $table);

        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        return $sql;
    }

    public function createCount(string $database, string $collection, array $filter = []): string
    {
        $table = $this->quoteIdentifier($database) . '.' . $this->quoteIdentifier($collection);
        $where = $this->buildWhere($filter);

        $sql = sprintf('SELECT COUNT(*) FROM %s', $table);

        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        return $sql;
    }

    public function createDistinct(string $database, string $collection, string $fieldName, array $filter = []): string
    {
        $table = $this->quoteIdentifier($database) . '.' . $this->quoteIdentifier($collection);
        $where = $this->buildWhere($filter);
        $fieldExpression = $fieldName === '_id' ? "data->'_id'" : sprintf('data->%s', $this->pdo->quote($fieldName));

        $sql = sprintf('SELECT DISTINCT %s FROM %s', $fieldExpression, $table);

        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        return $sql;
    }

    public function createAggregate(string $database, string $collection, array $pipeline, array $options = []): string
    {
        $table = $this->quoteIdentifier($database) . '.' . $this->quoteIdentifier($collection);
        $currentQuery = sprintf('SELECT data FROM %s', $table);

        foreach ($pipeline as $stage) {
            foreach ($stage as $operator => $value) {
                if ($operator === '$match') {
                    $where = $this->buildWhere($value);
                    if ($where) {
                        $currentQuery = sprintf('SELECT * FROM (%s) AS t WHERE %s', $currentQuery, str_replace('data', 't.data', $where));
                    }
                } elseif ($operator === '$sort') {
                    $sortParts = [];
                    foreach ($value as $field => $direction) {
                        $dir = $direction === 1 || $direction === 'asc' ? 'ASC' : 'DESC';
                        if ($field === '_id') {
                            $sortParts[] = sprintf("data->>'_id' %s", $dir);
                        } else {
                            $sortParts[] = sprintf('data->%s %s', $this->pdo->quote($field), $dir);
                        }
                    }

                    if (!empty($sortParts)) {
                        $currentQuery = sprintf('SELECT * FROM (%s) AS t ORDER BY %s', $currentQuery, implode(', ', $sortParts));
                    }
                } elseif ($operator === '$limit') {
                    $currentQuery = sprintf('SELECT * FROM (%s) AS t LIMIT %d', $currentQuery, (int)$value);
                } elseif ($operator === '$skip') {
                    $currentQuery = sprintf('SELECT * FROM (%s) AS t OFFSET %d', $currentQuery, (int)$value);
                } elseif ($operator === '$project') {
                    // For projection, we reuse the logic but need to apply it to the subquery
                    $projection = $value;
                    $include = [];
                    $exclude = [];
                    foreach ($projection as $field => $v) {
                        if ($v) {
                            $include[] = $field;
                        } else {
                            $exclude[] = $field;
                        }
                    }

                    $column = 'data';
                    if (!empty($include)) {
                        $fields = [];
                        foreach ($include as $field) {
                            $fields[] = $this->pdo->quote($field);
                            $fields[] = sprintf('data->%s', $this->pdo->quote($field));
                        }

                        if (!in_array('_id', $include, true) && (!isset($projection['_id']) || $projection['_id'])) {
                            $fields[] = $this->pdo->quote('_id');
                            $fields[] = "data->'_id'";
                        }

                        $column = sprintf('jsonb_build_object(%s)', implode(', ', $fields));
                    } elseif (!empty($exclude)) {
                        foreach ($exclude as $field) {
                            $column = sprintf('%s - %s', $column, $this->pdo->quote($field));
                        }
                    }

                    $currentQuery = sprintf('SELECT %s AS data FROM (%s) AS t', $column, $currentQuery);
                } elseif ($operator === '$unwind') {
                    $field = ltrim($value, '$');
                    $currentQuery = sprintf(
                        'SELECT jsonb_set(data, %1$s, x) AS data FROM (%2$s) AS t, jsonb_array_elements(CASE WHEN jsonb_typeof(data->%3$s) = \'array\' THEN data->%3$s ELSE \'[]\'::jsonb END) x',
                        $this->pdo->quote('{' . $field . '}'),
                        $currentQuery,
                        $this->pdo->quote($field),
                    );
                } elseif ($operator === '$group') {
                    $id = $value['_id'] ?? null;
                    $fields = [];
                    $groupBy = '1';
                    if ($id !== null) {
                        if (is_string($id) && str_starts_with($id, '$')) {
                            $groupBy = sprintf('data->%s', $this->pdo->quote(ltrim($id, '$')));
                        } else {
                            $groupBy = $this->pdo->quote(json_encode($id));
                        }
                    }

                    $fields[] = $this->pdo->quote('_id');
                    $fields[] = $groupBy;

                    foreach ($value as $alias => $expr) {
                        if ($alias === '_id') {
                            continue;
                        }

                        foreach ($expr as $sumOp => $sumVal) {
                            if ($sumOp !== '$sum') {
                                continue;
                            }

                            $fields[] = $this->pdo->quote($alias);
                            if (is_numeric($sumVal)) {
                                $fields[] = sprintf('SUM(%s)::text::jsonb', (float)$sumVal);
                            } else {
                                $fields[] = sprintf('SUM((data->>%s)::numeric)::text::jsonb', $this->pdo->quote(ltrim($sumVal, '$')));
                            }
                        }
                    }

                    $currentQuery = sprintf(
                        'SELECT jsonb_build_object(%s) AS data FROM (%s) AS t GROUP BY %s',
                        implode(', ', $fields),
                        $currentQuery,
                        $groupBy,
                    );
                }
            }
        }

        return $currentQuery;
    }

    private function buildWhere(array $filter): string
    {
        if (empty($filter)) {
            return '';
        }

        return $this->buildFilter($filter);
    }

    private function buildFilter(array $filter, string $conjunction = 'AND'): string
    {
        $parts = [];
        foreach ($filter as $key => $value) {
            if ($key === '$and') {
                $parts[] = '(' . $this->buildLogicalOperator($value, 'AND') . ')';
                continue;
            }

            if ($key === '$or') {
                $parts[] = '(' . $this->buildLogicalOperator($value, 'OR') . ')';
                continue;
            }

            if ($key === '$nor') {
                $parts[] = 'NOT (' . $this->buildLogicalOperator($value, 'OR') . ')';
                continue;
            }

            if ($key === '$not') {
                $parts[] = 'NOT (' . $this->buildFilter($value) . ')';
                continue;
            }

            if (str_starts_with($key, '$')) {
                throw new RuntimeException(sprintf('Operator "%s" is not supported at the top level', $key));
            }

            $parts[] = $this->buildFieldFilter($key, $value);
        }

        return implode(sprintf(' %s ', $conjunction), $parts);
    }

    private function buildLogicalOperator(array $queries, string $conjunction): string
    {
        $parts = [];
        foreach ($queries as $query) {
            $parts[] = $this->buildFilter($query);
        }

        return implode(sprintf(' %s ', $conjunction), $parts);
    }

    private function buildFieldFilter(string $field, mixed $value): string
    {
        if (is_array($value) && $this->isOperatorArray($value)) {
            $parts = [];
            foreach ($value as $operator => $operand) {
                if ($operator === '$options') {
                    continue;
                }

                $parts[] = $this->buildOperatorFilter($field, $operator, $operand, $value);
            }

            return implode(' AND ', $parts);
        }

        if ($field === '_id') {
            return sprintf("data->>'_id' = %s", $this->pdo->quote((string)$value));
        }

        return sprintf('data @> %s', $this->pdo->quote(json_encode([$field => $value])));
    }

    private function isOperatorArray(array $array): bool
    {
        foreach (array_keys($array) as $key) {
            if (str_starts_with((string)$key, '$')) {
                return true;
            }
        }

        return false;
    }

    private function buildOperatorFilter(string $field, string $operator, mixed $operand, array $allOperators = []): string
    {
        $fieldExpression = $field === '_id' ? "data->>'_id'" : sprintf('data->%s', $this->pdo->quote($field));

        if ($operator === '$not') {
            return sprintf('NOT (%s)', $this->buildFieldFilter($field, $operand));
        }

        if ($operator === '$eq') {
            if ($field === '_id') {
                return sprintf('%s = %s', $fieldExpression, $this->pdo->quote((string)$operand));
            }

            return sprintf('data @> %s', $this->pdo->quote(json_encode([$field => $operand])));
        }

        if ($operator === '$ne') {
            if ($field === '_id') {
                return sprintf('%s != %s', $fieldExpression, $this->pdo->quote((string)$operand));
            }

            return sprintf('NOT (data @> %s)', $this->pdo->quote(json_encode([$field => $operand])));
        }

        if ($operator === '$gt') {
            return sprintf('(%s) > %s::jsonb', $fieldExpression, $this->pdo->quote(json_encode($operand)));
        }

        if ($operator === '$gte') {
            return sprintf('(%s) >= %s::jsonb', $fieldExpression, $this->pdo->quote(json_encode($operand)));
        }

        if ($operator === '$lt') {
            return sprintf('(%s) < %s::jsonb', $fieldExpression, $this->pdo->quote(json_encode($operand)));
        }

        if ($operator === '$lte') {
            return sprintf('(%s) <= %s::jsonb', $fieldExpression, $this->pdo->quote(json_encode($operand)));
        }

        if ($operator === '$in') {
            if (!is_array($operand)) {
                throw new RuntimeException('$in requires an array');
            }

            if (empty($operand)) {
                return 'FALSE';
            }

            $values = array_map(
                fn ($val) => sprintf('%s::jsonb', $this->pdo->quote(json_encode($val))),
                $operand,
            );

            return sprintf('(%s) IN (%s)', $fieldExpression, implode(', ', $values));
        }

        if ($operator === '$nin') {
            if (!is_array($operand)) {
                throw new RuntimeException('$nin requires an array');
            }

            if (empty($operand)) {
                return 'TRUE';
            }

            $values = array_map(
                fn ($val) => sprintf('%s::jsonb', $this->pdo->quote(json_encode($val))),
                $operand,
            );

            return sprintf('(%s) NOT IN (%s)', $fieldExpression, implode(', ', $values));
        }

        if ($operator === '$exists') {
            if ($operand) {
                return sprintf('data ?? %s', $this->pdo->quote($field));
            }

            return sprintf('NOT (data ?? %s)', $this->pdo->quote($field));
        }

        if ($operator === '$regex') {
            // MongoDB $regex on field "name" where "name" is "Apple" matches "^App"
            // In PG, data->'name' is a jsonb string '"Apple"'.
            // We need the raw string value for regex.
            $rawFieldExpression = $field === '_id' ? "data->>'_id'" : sprintf('data->>%s', $this->pdo->quote($field));

            $caseInsensitive = false;
            if (isset($allOperators['$options']) && str_contains($allOperators['$options'], 'i')) {
                $caseInsensitive = true;
            }

            $op = $caseInsensitive ? '~*' : '~';

            return sprintf('%s %s %s', $rawFieldExpression, $op, $this->pdo->quote((string)$operand));
        }

        if ($operator === '$all') {
            if (!is_array($operand)) {
                throw new RuntimeException('$all requires an array');
            }

            return sprintf('%s @> %s', $fieldExpression, $this->pdo->quote(json_encode($operand)));
        }

        if ($operator === '$size') {
            return sprintf('jsonb_array_length(%s) = %d', $fieldExpression, (int)$operand);
        }

        throw new RuntimeException(sprintf('Operator "%s" is not supported', $operator));
    }

    public function createCreateIndex(string $database, string $collection, array $key, array $options): string
    {
        $table = $this->quoteIdentifier($database) . '.' . $this->quoteIdentifier($collection);
        $name = $options['name'] ?? null;

        if ($name === null) {
            $name = $database . '_' . $collection . '_' . implode('_', array_keys($key)) . '_idx';
        }

        $unique = isset($options['unique']) && $options['unique'] ? 'UNIQUE' : '';

        $parts = [];
        foreach ($key as $field => $direction) {
            $parts[] = sprintf("(data->%s)", $this->pdo->quote($field));
        }

        return sprintf(
            'CREATE %s INDEX IF NOT EXISTS %s ON %s (%s)',
            $unique,
            $this->quoteIdentifier($name),
            $table,
            implode(', ', $parts),
        );
    }

    public function createDropIndex(string $database, string $name): string
    {
        return sprintf(
            'DROP INDEX IF EXISTS %s.%s',
            $this->quoteIdentifier($database),
            $this->quoteIdentifier($name),
        );
    }

    public function createListIndexes(string $database, string $collection): string
    {
        return sprintf(
            "SELECT indexname as name FROM pg_indexes WHERE schemaname = %s AND tablename = %s",
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
            "SELECT table_name as name FROM information_schema.tables WHERE table_schema = %s",
            $this->pdo->quote($database),
        );
    }

    public function createRenameCollection(string $database, string $oldName, string $newName): string
    {
        return sprintf(
            'ALTER TABLE %s.%s RENAME TO %s',
            $this->quoteIdentifier($database),
            $this->quoteIdentifier($oldName),
            $this->quoteIdentifier($newName),
        );
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
