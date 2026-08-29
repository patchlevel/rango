<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Query;

use Patchlevel\Rango\Sql\Identifier;
use PDO;

use function explode;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;
use function json_encode;
use function ltrim;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;

final readonly class AggregationBuilder
{
    public function __construct(
        private PDO $pdo,
        private FilterBuilder $filterBuilder,
        private ProjectionBuilder $projectionBuilder,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $pipeline
     * @param array<string, mixed>       $options
     */
    public function createAggregate(string $database, string $collection, array $pipeline, array $options = []): string
    {
        $table = Identifier::quote($database) . '.' . Identifier::quote($collection);
        $currentQuery = sprintf('SELECT data FROM %s', $table);

        foreach ($pipeline as $stage) {
            foreach ($stage as $operator => $value) {
                if ($operator === '$match') {
                    $where = $this->filterBuilder->buildWhere($value);
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
                    $column = $this->projectionBuilder->buildProjectionColumn($value, 'data');
                    $currentQuery = sprintf('SELECT %s AS data FROM (%s) AS t', $column, $currentQuery);
                } elseif ($operator === '$unwind') {
                    $spec = is_array($value) ? $value : ['path' => $value];
                    $field = ltrim($spec['path'], '$');
                    $preserve = (bool)($spec['preserveNullAndEmptyArrays'] ?? false);
                    $includeArrayIndex = $spec['includeArrayIndex'] ?? null;

                    $source = $this->fieldReference($field);
                    $arrayExpr = sprintf(
                        'CASE WHEN jsonb_typeof(%1$s) = \'array\' THEN %1$s'
                        . ' WHEN %1$s IS NULL OR jsonb_typeof(%1$s) = \'null\' THEN \'[]\'::jsonb'
                        . ' ELSE jsonb_build_array(%1$s) END',
                        $source,
                    );

                    $dataExpr = sprintf(
                        'CASE WHEN u.elem IS NULL THEN data ELSE jsonb_set(data, %s, u.elem, true) END',
                        $this->pathLiteral($field),
                    );

                    if (is_string($includeArrayIndex) && $includeArrayIndex !== '') {
                        $dataExpr = sprintf(
                            'jsonb_set(%s, %s, CASE WHEN u.ord IS NULL THEN \'null\'::jsonb ELSE to_jsonb(u.ord - 1) END, true)',
                            $dataExpr,
                            $this->pathLiteral(ltrim($includeArrayIndex, '$')),
                        );
                    }

                    $currentQuery = sprintf(
                        'SELECT %s AS data FROM (%s) AS t %s LATERAL jsonb_array_elements(%s) WITH ORDINALITY AS u(elem, ord) ON true',
                        $dataExpr,
                        $currentQuery,
                        $preserve ? 'LEFT JOIN' : 'INNER JOIN',
                        $arrayExpr,
                    );
                } elseif ($operator === '$group') {
                    $groupBy = $this->groupKey($value['_id'] ?? null);

                    $fields = [$this->pdo->quote('_id'), $groupBy];

                    foreach ($value as $alias => $expr) {
                        if ($alias === '_id') {
                            continue;
                        }

                        foreach ($expr as $accOp => $accVal) {
                            $accumulator = $this->accumulator($accOp, $accVal);
                            if ($accumulator === null) {
                                continue;
                            }

                            $fields[] = $this->pdo->quote($alias);
                            $fields[] = $accumulator;
                        }
                    }

                    $currentQuery = sprintf(
                        'SELECT jsonb_build_object(%s) AS data FROM (%s) AS t GROUP BY %s',
                        implode(', ', $fields),
                        $currentQuery,
                        $groupBy,
                    );
                } elseif ($operator === '$count') {
                    $currentQuery = sprintf(
                        'SELECT jsonb_build_object(%s, COUNT(*)::text::jsonb) AS data FROM (%s) AS t HAVING COUNT(*) > 0',
                        $this->pdo->quote($value),
                        $currentQuery,
                    );
                } elseif ($operator === '$lookup') {
                    $from = $value['from'];
                    $localField = $value['localField'];
                    $foreignField = $value['foreignField'];
                    $as = $value['as'];

                    $foreignTable = Identifier::quote($database) . '.' . Identifier::quote($from);

                    $localFieldExpression = 't.data';
                    if ($localField === '_id') {
                        $localFieldExpression = "t.data->'_id'";
                    } else {
                        foreach (explode('.', $localField) as $part) {
                            $localFieldExpression = sprintf('%s->%s', $localFieldExpression, $this->pdo->quote($part));
                        }
                    }

                    $foreignFieldExpression = 'data';
                    $joinExpression = sprintf('%s = %s', $foreignFieldExpression, $localFieldExpression);
                    if ($foreignField === '_id') {
                        $foreignFieldExpression = '_id';
                        $joinExpression = sprintf("%s = (%s #>> '{}')", $foreignFieldExpression, $localFieldExpression);
                    } else {
                        foreach (explode('.', $foreignField) as $part) {
                            $foreignFieldExpression = sprintf('%s->%s', $foreignFieldExpression, $this->pdo->quote($part));
                        }

                        $joinExpression = sprintf('%s = %s', $foreignFieldExpression, $localFieldExpression);
                    }

                    $currentQuery = sprintf(
                        'SELECT t.data || jsonb_build_object(%s, COALESCE(l.matches, \'[]\'::jsonb)) AS data
                         FROM (%s) AS t
                         LEFT JOIN LATERAL (
                             SELECT jsonb_agg(data) AS matches
                             FROM %s
                             WHERE %s
                         ) l ON true',
                        $this->pdo->quote($as),
                        $currentQuery,
                        $foreignTable,
                        $joinExpression,
                    );
                }
            }
        }

        return $currentQuery;
    }

    private function groupKey(mixed $id): string
    {
        if ($id === null) {
            return "'null'::jsonb";
        }

        if (is_array($id)) {
            $parts = [];
            foreach ($id as $key => $expr) {
                $parts[] = $this->pdo->quote((string)$key);
                $parts[] = $this->valueExpression($expr);
            }

            return sprintf('jsonb_build_object(%s)', implode(', ', $parts));
        }

        return $this->valueExpression($id);
    }

    private function accumulator(mixed $operator, mixed $value): string|null
    {
        return match ($operator) {
            '$sum' => is_numeric($value)
                ? sprintf('COALESCE(SUM(%s), 0)::text::jsonb', (float)$value)
                : sprintf('COALESCE(SUM((%s)::numeric), 0)::text::jsonb', $this->fieldReferenceText(ltrim($value, '$'))),
            '$avg' => sprintf('AVG((%s)::numeric)::text::jsonb', $this->fieldReferenceText(ltrim($value, '$'))),
            '$min' => sprintf('MIN((%s)::numeric)::text::jsonb', $this->fieldReferenceText(ltrim($value, '$'))),
            '$max' => sprintf('MAX((%s)::numeric)::text::jsonb', $this->fieldReferenceText(ltrim($value, '$'))),
            '$first' => sprintf('(ARRAY_AGG(%s))[1]', $this->fieldReference(ltrim($value, '$'))),
            '$last' => sprintf(
                '(ARRAY_AGG(%1$s))[ARRAY_LENGTH(ARRAY_AGG(%1$s), 1)]',
                $this->fieldReference(ltrim($value, '$')),
            ),
            '$push' => sprintf('jsonb_agg(%s)', $this->valueExpression($value)),
            '$addToSet' => sprintf('jsonb_agg(DISTINCT %s)', $this->valueExpression($value)),
            '$count' => 'COUNT(*)::text::jsonb',
            default => null,
        };
    }

    private function valueExpression(mixed $value): string
    {
        if (is_string($value) && str_starts_with($value, '$')) {
            return $this->fieldReference(ltrim($value, '$'));
        }

        return sprintf('%s::jsonb', $this->pdo->quote(json_encode($value)));
    }

    private function fieldReference(string $path): string
    {
        if (!str_contains($path, '.')) {
            return sprintf('data->%s', $this->pdo->quote($path));
        }

        return sprintf('data#>%s', $this->pathLiteral($path));
    }

    private function fieldReferenceText(string $path): string
    {
        if (!str_contains($path, '.')) {
            return sprintf('data->>%s', $this->pdo->quote($path));
        }

        return sprintf('data#>>%s', $this->pathLiteral($path));
    }

    private function pathLiteral(string $path): string
    {
        return $this->pdo->quote('{' . str_replace('.', ',', $path) . '}');
    }
}
