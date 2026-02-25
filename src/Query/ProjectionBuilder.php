<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Query;

use PDO;

use function explode;
use function implode;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

final class ProjectionBuilder
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /** @param array<string, mixed> $projection */
    public function buildProjectionColumn(array $projection, string $column = 'data'): string
    {
        if (empty($projection)) {
            return $column;
        }

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
            $fields = [];
            $topLevelInclusions = [];

            foreach ($include as $field) {
                if ($field === '_id') {
                    $topLevelInclusions['_id'] = $column . "->'_id'";
                    continue;
                }

                $parts = explode('.', $field);
                $topKey = $parts[0];

                if (isset($topLevelInclusions[$topKey])) {
                    continue;
                }

                $topLevelInclusions[$topKey] = $this->buildNestedProjectionForTopKey($topKey, $include, $column);
            }

            if (!isset($topLevelInclusions['_id']) && (!isset($projection['_id']) || $projection['_id'])) {
                $topLevelInclusions['_id'] = $column . "->'_id'";
            }

            foreach ($topLevelInclusions as $key => $expr) {
                $fields[] = $this->pdo->quote($key);
                $fields[] = $expr;
            }

            return sprintf('jsonb_build_object(%s)', implode(', ', $fields));
        }

        if (!empty($exclude)) {
            $result = $column;
            foreach ($exclude as $field) {
                $result = sprintf('%s - %s', $result, $this->pdo->quote($field));
            }

            return $result;
        }

        return $column;
    }

    /** @param list<string> $allInclusions */
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

            $subTopLevel[$subKey] = $this->buildNestedProjectionForTopKey(
                $subKey,
                $relevant,
                sprintf('%s->%s', $column, $this->pdo->quote($topKey)),
            );
        }

        foreach ($subTopLevel as $key => $expr) {
            $fields[] = $this->pdo->quote($key);
            $fields[] = $expr;
        }

        return sprintf('jsonb_build_object(%s)', implode(', ', $fields));
    }
}
