<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Query;

use Patchlevel\Rango\Sql\Identifier;
use PDO;

use function explode;
use function implode;
use function is_numeric;
use function is_string;
use function json_encode;
use function ltrim;
use function sprintf;
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
}
