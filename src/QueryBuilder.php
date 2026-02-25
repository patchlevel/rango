<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use PDO;
use RuntimeException;

use function array_keys;
use function array_map;
use function array_merge;
use function array_pop;
use function count;
use function explode;
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
use function strlen;
use function substr;

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
                $topLevelInclusions = [];

                foreach ($include as $field) {
                    if ($field === '_id') {
                        $topLevelInclusions['_id'] = "data->'_id'";
                        continue;
                    }

                    $parts = explode('.', $field);
                    $topKey = $parts[0];

                    if (isset($topLevelInclusions[$topKey])) {
                        continue;
                    }

                    $topLevelInclusions[$topKey] = $this->buildNestedProjectionForTopKey($topKey, $include, 'data');
                }

                if (!isset($topLevelInclusions['_id']) && (!isset($projection['_id']) || $projection['_id'])) {
                    $topLevelInclusions['_id'] = "data->'_id'";
                }

                foreach ($topLevelInclusions as $key => $expr) {
                    $fields[] = $this->pdo->quote($key);
                    $fields[] = $expr;
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

    public function createUpdate(string $database, string $collection, array $filter, array $update, array $options = [], bool $multi = false): string
    {
        $table = $this->quoteIdentifier($database) . '.' . $this->quoteIdentifier($collection);
        $where = $this->buildWhere($filter);

        $setData = $update['$set'] ?? null;
        $incData = $update['$inc'] ?? null;
        $unsetData = $update['$unset'] ?? null;
        $pushData = $update['$push'] ?? null;
        $pullData = $update['$pull'] ?? null;
        $addToSetData = $update['$addToSet'] ?? null;
        $popData = $update['$pop'] ?? null;
        $bitData = $update['$bit'] ?? null;
        $currentDateData = $update['$currentDate'] ?? null;
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
                if (is_array($value) && isset($value['$each'])) {
                    $pushValue = json_encode($value['$each']);
                } else {
                    $pushValue = json_encode([$value]);
                }

                $updateParts[] = sprintf(
                    'jsonb_set(data, %s, COALESCE(data->%s, \'[]\'::jsonb) || %s)',
                    $this->pdo->quote('{' . $field . '}'),
                    $this->pdo->quote($field),
                    $this->pdo->quote($pushValue),
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

        if ($addToSetData) {
            foreach ($addToSetData as $field => $value) {
                $updateParts[] = sprintf(
                    'jsonb_set(data, %1$s, COALESCE(data->%2$s, \'[]\'::jsonb) || (CASE WHEN (data->%2$s) @> %3$s THEN \'[]\'::jsonb ELSE %3$s::jsonb END))',
                    $this->pdo->quote('{' . $field . '}'),
                    $this->pdo->quote($field),
                    $this->pdo->quote(json_encode([$value])),
                );
            }
        }

        if ($popData) {
            foreach ($popData as $field => $value) {
                if ($value === 1) {
                    // Remove last element
                    $updateParts[] = sprintf(
                        'jsonb_set(data, %1$s, (data->%2$s) - (jsonb_array_length(data->%2$s) - 1))',
                        $this->pdo->quote('{' . $field . '}'),
                        $this->pdo->quote($field),
                    );
                } elseif ($value === -1) {
                    // Remove first element
                    $updateParts[] = sprintf(
                        'jsonb_set(data, %1$s, (data->%2$s) - 0)',
                        $this->pdo->quote('{' . $field . '}'),
                        $this->pdo->quote($field),
                    );
                }
            }
        }

        if ($bitData) {
            foreach ($bitData as $field => $operations) {
                foreach ($operations as $op => $value) {
                    $sqlOp = match ($op) {
                        'and' => '&',
                        'or' => '|',
                        'xor' => '#',
                        default => throw new RuntimeException(sprintf('Bitwise operator "%s" is not supported', $op)),
                    };

                    $updateParts[] = sprintf(
                        'jsonb_set(data, %s, (COALESCE(data->>%s, \'0\')::bigint %s %d)::text::jsonb)',
                        $this->pdo->quote('{' . $field . '}'),
                        $this->pdo->quote($field),
                        $sqlOp,
                        (int)$value,
                    );
                }
            }
        }

        if ($currentDateData) {
            foreach ($currentDateData as $field => $value) {
                $updateParts[] = sprintf(
                    'jsonb_set(data, %s, to_jsonb(CURRENT_TIMESTAMP))',
                    $this->pdo->quote('{' . $field . '}'),
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

                        foreach ($expr as $accOp => $accVal) {
                            if ($accOp === '$sum') {
                                $fields[] = $this->pdo->quote($alias);
                                if (is_numeric($accVal)) {
                                    $fields[] = sprintf('SUM(%s)::text::jsonb', (float)$accVal);
                                } else {
                                    $fields[] = sprintf('SUM((data->>%s)::numeric)::text::jsonb', $this->pdo->quote(ltrim($accVal, '$')));
                                }
                            } elseif ($accOp === '$avg') {
                                $fields[] = $this->pdo->quote($alias);
                                $fields[] = sprintf('AVG((data->>%s)::numeric)::text::jsonb', $this->pdo->quote(ltrim($accVal, '$')));
                            } elseif ($accOp === '$min') {
                                $fields[] = $this->pdo->quote($alias);
                                $fields[] = sprintf('MIN((data->>%s)::numeric)::text::jsonb', $this->pdo->quote(ltrim($accVal, '$')));
                            } elseif ($accOp === '$max') {
                                $fields[] = $this->pdo->quote($alias);
                                $fields[] = sprintf('MAX((data->>%s)::numeric)::text::jsonb', $this->pdo->quote(ltrim($accVal, '$')));
                            } elseif ($accOp === '$first') {
                                $fields[] = $this->pdo->quote($alias);
                                $fields[] = sprintf('(ARRAY_AGG(data->%s))[1]', $this->pdo->quote(ltrim($accVal, '$')));
                            } elseif ($accOp === '$last') {
                                $fields[] = $this->pdo->quote($alias);
                                $fields[] = sprintf('(ARRAY_AGG(data->%s))[ARRAY_LENGTH(ARRAY_AGG(data->%s), 1)]', $this->pdo->quote(ltrim($accVal, '$')), $this->pdo->quote(ltrim($accVal, '$')));
                            }
                        }
                    }

                    $currentQuery = sprintf(
                        'SELECT jsonb_build_object(%s) AS data FROM (%s) AS t GROUP BY %s',
                        implode(', ', $fields),
                        $currentQuery,
                        $groupBy,
                    );
                } elseif ($operator === '$lookup') {
                    $from = $value['from'];
                    $localField = $value['localField'];
                    $foreignField = $value['foreignField'];
                    $as = $value['as'];

                    $foreignTable = $this->quoteIdentifier($database) . '.' . $this->quoteIdentifier($from);

                    $localFieldExpression = 't.data';
                    if ($localField === '_id') {
                        $localFieldExpression = "t.data->'_id'";
                    } else {
                        foreach (explode('.', $localField) as $part) {
                            $localFieldExpression = sprintf('%s->%s', $localFieldExpression, $this->pdo->quote($part));
                        }
                    }

                    $foreignFieldExpression = 'data';
                    if ($foreignField === '_id') {
                        $foreignFieldExpression = "data->'_id'";
                    } else {
                        foreach (explode('.', $foreignField) as $part) {
                            $foreignFieldExpression = sprintf('%s->%s', $foreignFieldExpression, $this->pdo->quote($part));
                        }
                    }

                    $currentQuery = sprintf(
                        'SELECT t.data || jsonb_build_object(%s, COALESCE(l.matches, \'[]\'::jsonb)) AS data
                         FROM (%s) AS t
                         LEFT JOIN LATERAL (
                             SELECT jsonb_agg(data) AS matches
                             FROM %s
                             WHERE %s = %s
                         ) l ON true',
                        $this->pdo->quote($as),
                        $currentQuery,
                        $foreignTable,
                        $foreignFieldExpression,
                        $localFieldExpression,
                    );
                }
            }
        }

        return $currentQuery;
    }

    private function buildWhere(array $filter, string $conjunction = 'AND', string $column = 'data'): string
    {
        if (empty($filter)) {
            return '';
        }

        return $this->buildFilter($filter, $conjunction, $column);
    }

    private function buildFilter(array $filter, string $conjunction = 'AND', string $column = 'data'): string
    {
        $parts = [];
        foreach ($filter as $key => $value) {
            if ($key === '$and') {
                $parts[] = '(' . $this->buildLogicalOperator($value, 'AND', $column) . ')';
                continue;
            }

            if ($key === '$or') {
                $parts[] = '(' . $this->buildLogicalOperator($value, 'OR', $column) . ')';
                continue;
            }

            if ($key === '$nor') {
                $parts[] = 'NOT (' . $this->buildLogicalOperator($value, 'OR', $column) . ')';
                continue;
            }

            if ($key === '$not') {
                $parts[] = 'NOT (' . $this->buildFilter($value, 'AND', $column) . ')';
                continue;
            }

            if (str_starts_with($key, '$')) {
                throw new RuntimeException(sprintf('Operator "%s" is not supported at the top level', $key));
            }

            $parts[] = $this->buildFieldFilter($key, $value, $column);
        }

        return implode(sprintf(' %s ', $conjunction), $parts);
    }

    private function buildLogicalOperator(array $queries, string $conjunction, string $column = 'data'): string
    {
        $parts = [];
        foreach ($queries as $query) {
            $parts[] = $this->buildFilter($query, 'AND', $column);
        }

        return implode(sprintf(' %s ', $conjunction), $parts);
    }

    private function buildFieldFilter(string $field, mixed $value, string $column = 'data'): string
    {
        if (is_array($value) && $this->isOperatorArray($value)) {
            $parts = [];
            foreach ($value as $operator => $operand) {
                if ($operator === '$options') {
                    continue;
                }

                $parts[] = $this->buildOperatorFilter($field, $operator, $operand, $value, $column);
            }

            return implode(' AND ', $parts);
        }

        if ($field === '_id') {
            return sprintf("%s->>'_id' = %s", $column, $this->pdo->quote((string)$value));
        }

        if (str_contains($field, '.')) {
            $parts = explode('.', $field);
            $expression = $column;
            $lastKey = array_pop($parts);
            foreach ($parts as $part) {
                $expression = sprintf('%s->%s', $expression, $this->pdo->quote($part));
            }

            return sprintf('%s @> %s', $expression, $this->pdo->quote(json_encode([$lastKey => $value])));
        }

        return sprintf('%s @> %s', $column, $this->pdo->quote(json_encode([$field => $value])));
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

    private function buildOperatorFilter(string $field, string $operator, mixed $operand, array $allOperators = [], string $column = 'data'): string
    {
        $fieldExpression = $field === '_id' ? sprintf("%s->>'_id'", $column) : sprintf('%s->%s', $column, $this->pdo->quote($field));

        if (str_contains($field, '.') && $field !== '_id') {
            $parts = explode('.', $field);
            $fieldExpression = $column;
            foreach ($parts as $part) {
                $fieldExpression = sprintf('%s->%s', $fieldExpression, $this->pdo->quote($part));
            }
        }

        if ($operator === '$not') {
            return sprintf('NOT (%s)', $this->buildFieldFilter($field, $operand, $column));
        }

        if ($operator === '$eq') {
            if ($field === '_id') {
                return sprintf('%s = %s', $fieldExpression, $this->pdo->quote((string)$operand));
            }

            return sprintf('%s @> %s', $column, $this->pdo->quote(json_encode([$field => $operand])));
        }

        if ($operator === '$ne') {
            if ($field === '_id') {
                return sprintf('%s != %s', $fieldExpression, $this->pdo->quote((string)$operand));
            }

            return sprintf('NOT (%s @> %s)', $column, $this->pdo->quote(json_encode([$field => $operand])));
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
                return sprintf('%s ?? %s', $column, $this->pdo->quote($field));
            }

            return sprintf('NOT (%s ?? %s)', $column, $this->pdo->quote($field));
        }

        if ($operator === '$regex') {
            // MongoDB $regex on field "name" where "name" is "Apple" matches "^App"
            // In PG, data->'name' is a jsonb string '"Apple"'.
            // We need the raw string value for regex.
            $rawFieldExpression = $field === '_id' ? sprintf("%s->>'_id'", $column) : sprintf('%s->>%s', $column, $this->pdo->quote($field));

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

        if ($operator === '$type') {
            $typeMap = [
                'double' => 'number',
                'string' => 'string',
                'object' => 'object',
                'array' => 'array',
                'bool' => 'boolean',
                'boolean' => 'boolean',
                'number' => 'number',
                'int' => 'number',
                'long' => 'number',
                'decimal' => 'number',
                'null' => 'null',
            ];

            $pgType = $typeMap[$operand] ?? $operand;

            return sprintf('jsonb_typeof(%s) = %s', $fieldExpression, $this->pdo->quote((string)$pgType));
        }

        if ($operator === '$mod') {
            if (!is_array($operand) || count($operand) !== 2) {
                throw new RuntimeException('$mod requires an array with 2 elements');
            }

            return sprintf('MOD((%s)::numeric, %d) = %d', $fieldExpression, (int)$operand[0], (int)$operand[1]);
        }

        if ($operator === '$elemMatch') {
            if (!is_array($operand)) {
                throw new RuntimeException('$elemMatch requires an array/object');
            }

            $subWhere = $this->buildWhere($operand, 'AND', 'x');

            return sprintf(
                'EXISTS (SELECT 1 FROM jsonb_array_elements(%s) x WHERE %s)',
                $fieldExpression,
                $subWhere,
            );
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
            $parts[] = sprintf('(data->%s)', $this->pdo->quote($field));
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
            $this->quoteIdentifier($database),
            $this->quoteIdentifier($oldName),
            $this->quoteIdentifier($newName),
        );
    }

    private function buildNestedProjectionForTopKey(string $topKey, array $allInclusions, string $column): string
    {
        $relevant = [];
        foreach ($allInclusions as $field) {
            if ($field === $topKey) {
                return sprintf('%s->%s', $column, $this->pdo->quote($topKey));
            }

            if (!str_starts_with($field, $topKey . '.')) {
                continue;
            }

            $relevant[] = substr($field, strlen($topKey) + 1);
        }

        if (empty($relevant)) {
            return sprintf('%s->%s', $column, $this->pdo->quote($topKey));
        }

        $fields = [];
        $subTopLevel = [];
        foreach ($relevant as $field) {
            $parts = explode('.', $field);
            $subKey = $parts[0];

            if (isset($subTopLevel[$subKey])) {
                continue;
            }

            $subTopLevel[$subKey] = $this->buildNestedProjectionForTopKey($subKey, $relevant, sprintf('%s->%s', $column, $this->pdo->quote($topKey)));
        }

        foreach ($subTopLevel as $key => $expr) {
            $fields[] = $this->pdo->quote($key);
            $fields[] = $expr;
        }

        return sprintf('jsonb_build_object(%s)', implode(', ', $fields));
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
