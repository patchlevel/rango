<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Query;

use PDO;
use RuntimeException;

use function array_merge;
use function is_array;
use function json_encode;
use function sprintf;
use function str_replace;
use function str_starts_with;

final class UpdateBuilder
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     *
     * @return array{expression: string, setData: array<string, mixed>|null}
     */
    public function buildUpdateExpression(array $update): array
    {
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
                    $updateParts[] = sprintf(
                        'jsonb_set(data, %1$s, (data->%2$s) - (jsonb_array_length(data->%2$s) - 1))',
                        $this->pdo->quote('{' . $field . '}'),
                        $this->pdo->quote($field),
                    );
                } elseif ($value === -1) {
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

        $dataExpression = 'data';
        foreach ($updateParts as $part) {
            if (str_starts_with($part, 'data ')) {
                $dataExpression = str_replace('data ', $dataExpression . ' ', $part);
            } else {
                $dataExpression = str_replace('data,', $dataExpression . ',', $part);
            }
        }

        return [
            'expression' => $dataExpression,
            'setData' => is_array($setData) ? $setData : null,
        ];
    }

    /**
     * @param array<string, mixed>      $filter
     * @param array<string, mixed>|null $setData
     *
     * @return array<string, mixed>
     */
    public function buildUpsertDocument(array $filter, array|null $setData): array
    {
        return array_merge($filter, $setData ?? []);
    }
}
