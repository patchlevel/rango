<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Query;

use PDO;
use RuntimeException;

use function array_keys;
use function array_map;
use function array_pop;
use function count;
use function explode;
use function implode;
use function is_array;
use function json_encode;
use function sprintf;
use function str_contains;
use function str_starts_with;

final class FilterBuilder
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /** @param array<string, mixed> $filter */
    public function buildWhere(array $filter, string $conjunction = 'AND', string $column = 'data'): string
    {
        if (empty($filter)) {
            return '';
        }

        return $this->buildFilter($filter, $conjunction, $column);
    }

    /** @param array<string, mixed> $filter */
    public function buildFilter(array $filter, string $conjunction = 'AND', string $column = 'data'): string
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

    /** @param list<array<string, mixed>> $queries */
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

    /** @param array<string, mixed> $array */
    private function isOperatorArray(array $array): bool
    {
        foreach (array_keys($array) as $key) {
            if (str_starts_with((string)$key, '$')) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $allOperators */
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
}
