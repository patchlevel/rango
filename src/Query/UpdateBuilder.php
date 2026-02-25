<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Query;

use PDO;
use RuntimeException;

use function array_merge;
use function array_pop;
use function explode;
use function implode;
use function is_array;
use function json_encode;
use function sprintf;
use function str_contains;
use function str_replace;

final class UpdateBuilder
{
    private const DATA_PLACEHOLDER = '__DATA__';

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @param array<string, mixed> $update
     *
     * @return array{expression: string, setData: array<string, mixed>|null, setOnInsertData: array<string, mixed>|null}
     */
    public function buildUpdateExpression(array $update): array
    {
        $setData = $update['$set'] ?? null;
        $setOnInsertData = $update['$setOnInsert'] ?? null;
        $incData = $update['$inc'] ?? null;
        $mulData = $update['$mul'] ?? null;
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
            foreach ($setData as $field => $value) {
                $baseExpression = $this->baseExpression($field, self::DATA_PLACEHOLDER);
                $updateParts[] = sprintf(
                    'jsonb_set(%s, %s, %s::jsonb, true)',
                    $baseExpression,
                    $this->pathLiteral($field),
                    $this->pdo->quote(json_encode($value)),
                );
            }
        }

        if ($incData) {
            foreach ($incData as $field => $value) {
                $baseExpression = $this->baseExpression($field, self::DATA_PLACEHOLDER);
                $updateParts[] = sprintf(
                    'jsonb_set(%s, %s, (COALESCE(%s, \'0\')::numeric + %s)::text::jsonb, true)',
                    $baseExpression,
                    $this->pathLiteral($field),
                    $this->jsonbExtractText($field, $baseExpression),
                    (float)$value,
                );
            }
        }

        if ($mulData) {
            foreach ($mulData as $field => $value) {
                $baseExpression = $this->baseExpression($field, self::DATA_PLACEHOLDER);
                $updateParts[] = sprintf(
                    'jsonb_set(%s, %s, (COALESCE(%s, \'0\')::numeric * %s)::text::jsonb, true)',
                    $baseExpression,
                    $this->pathLiteral($field),
                    $this->jsonbExtractText($field, $baseExpression),
                    (float)$value,
                );
            }
        }

        if ($unsetData) {
            foreach ($unsetData as $field => $value) {
                $updateParts[] = sprintf('%s #- %s', self::DATA_PLACEHOLDER, $this->pathLiteral($field));
            }
        }

        if ($renameData) {
            foreach ($renameData as $oldField => $newField) {
                $baseExpression = $this->baseExpression(
                    $newField,
                    sprintf('%s #- %s', self::DATA_PLACEHOLDER, $this->pathLiteral($oldField)),
                );
                $updateParts[] = sprintf(
                    'jsonb_set(%s, %s, %s, true)',
                    $baseExpression,
                    $this->pathLiteral($newField),
                    $this->jsonbExtract($oldField, self::DATA_PLACEHOLDER),
                );
            }
        }

        if ($minData) {
            foreach ($minData as $field => $value) {
                $baseExpression = $this->baseExpression($field, self::DATA_PLACEHOLDER);
                $updateParts[] = sprintf(
                    'jsonb_set(%s, %s, LEAST(%s, %s::jsonb), true)',
                    $baseExpression,
                    $this->pathLiteral($field),
                    $this->jsonbExtract($field, $baseExpression),
                    $this->pdo->quote(json_encode($value)),
                );
            }
        }

        if ($maxData) {
            foreach ($maxData as $field => $value) {
                $baseExpression = $this->baseExpression($field, self::DATA_PLACEHOLDER);
                $updateParts[] = sprintf(
                    'jsonb_set(%s, %s, GREATEST(%s, %s::jsonb), true)',
                    $baseExpression,
                    $this->pathLiteral($field),
                    $this->jsonbExtract($field, $baseExpression),
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

                $baseExpression = $this->baseExpression($field, self::DATA_PLACEHOLDER);
                $updateParts[] = sprintf(
                    'jsonb_set(%s, %s, COALESCE(%s, \'[]\'::jsonb) || %s, true)',
                    $baseExpression,
                    $this->pathLiteral($field),
                    $this->jsonbExtract($field, $baseExpression),
                    $this->pdo->quote($pushValue),
                );
            }
        }

        if ($pullData) {
            foreach ($pullData as $field => $value) {
                $baseExpression = $this->baseExpression($field, self::DATA_PLACEHOLDER);
                $updateParts[] = sprintf(
                    'jsonb_set(%s, %s, COALESCE((SELECT jsonb_agg(x) FROM jsonb_array_elements(%s) x WHERE x != %s), \'[]\'::jsonb), true)',
                    $baseExpression,
                    $this->pathLiteral($field),
                    $this->jsonbExtract($field, $baseExpression),
                    $this->pdo->quote(json_encode($value)),
                );
            }
        }

        if ($addToSetData) {
            foreach ($addToSetData as $field => $value) {
                $baseExpression = $this->baseExpression($field, self::DATA_PLACEHOLDER);
                $updateParts[] = sprintf(
                    'jsonb_set(%1$s, %2$s, COALESCE(%3$s, \'[]\'::jsonb) || (CASE WHEN (%3$s) @> %4$s THEN \'[]\'::jsonb ELSE %4$s::jsonb END), true)',
                    $baseExpression,
                    $this->pathLiteral($field),
                    $this->jsonbExtract($field, $baseExpression),
                    $this->pdo->quote(json_encode([$value])),
                );
            }
        }

        if ($popData) {
            foreach ($popData as $field => $value) {
                $baseExpression = $this->baseExpression($field, self::DATA_PLACEHOLDER);
                if ($value === 1) {
                    $updateParts[] = sprintf(
                        'jsonb_set(%1$s, %2$s, (%3$s) - (jsonb_array_length(%3$s) - 1), true)',
                        $baseExpression,
                        $this->pathLiteral($field),
                        $this->jsonbExtract($field, $baseExpression),
                    );
                } elseif ($value === -1) {
                    $updateParts[] = sprintf(
                        'jsonb_set(%1$s, %2$s, (%3$s) - 0, true)',
                        $baseExpression,
                        $this->pathLiteral($field),
                        $this->jsonbExtract($field, $baseExpression),
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

                    $baseExpression = $this->baseExpression($field, self::DATA_PLACEHOLDER);
                    $updateParts[] = sprintf(
                        'jsonb_set(%s, %s, (COALESCE(%s, \'0\')::bigint %s %d)::text::jsonb, true)',
                        $baseExpression,
                        $this->pathLiteral($field),
                        $this->jsonbExtractText($field, $baseExpression),
                        $sqlOp,
                        (int)$value,
                    );
                }
            }
        }

        if ($currentDateData) {
            foreach ($currentDateData as $field => $value) {
                $baseExpression = $this->baseExpression($field, self::DATA_PLACEHOLDER);
                $updateParts[] = sprintf(
                    'jsonb_set(%s, %s, to_jsonb(CURRENT_TIMESTAMP), true)',
                    $baseExpression,
                    $this->pathLiteral($field),
                );
            }
        }

        if (empty($updateParts)) {
            throw new RuntimeException('No update operators found');
        }

        $dataExpression = 'data';
        foreach ($updateParts as $part) {
            $dataExpression = str_replace(self::DATA_PLACEHOLDER, $dataExpression, $part);
        }

        return [
            'expression' => $dataExpression,
            'setData' => is_array($setData) ? $setData : null,
            'setOnInsertData' => is_array($setOnInsertData) ? $setOnInsertData : null,
        ];
    }

    /**
     * @param array<string, mixed>      $filter
     * @param array<string, mixed>|null $setData
     * @param array<string, mixed>|null $setOnInsertData
     *
     * @return array<string, mixed>
     */
    public function buildUpsertDocument(array $filter, array|null $setData, array|null $setOnInsertData): array
    {
        return array_merge($filter, $setData ?? [], $setOnInsertData ?? []);
    }

    private function pathLiteral(string $field): string
    {
        $path = '{' . str_replace('.', ',', $field) . '}';

        return $this->pdo->quote($path) . '::text[]';
    }

    private function jsonbExtract(string $field, string $baseExpression): string
    {
        if (str_contains($field, '.')) {
            return sprintf('(%s)#>%s', $baseExpression, $this->pathLiteral($field));
        }

        return sprintf('(%s)->%s', $baseExpression, $this->pdo->quote($field));
    }

    private function jsonbExtractText(string $field, string $baseExpression): string
    {
        if (str_contains($field, '.')) {
            return sprintf('(%s)#>>%s', $baseExpression, $this->pathLiteral($field));
        }

        return sprintf('(%s)->>%s', $baseExpression, $this->pdo->quote($field));
    }

    private function baseExpression(string $field, string $baseExpression): string
    {
        if (!str_contains($field, '.')) {
            return $baseExpression;
        }

        $parts = explode('.', $field);
        array_pop($parts);

        $expression = $baseExpression;
        $path = [];
        foreach ($parts as $part) {
            $path[] = $part;
            $pathLiteral = $this->pathLiteral(implode('.', $path));
            $expression = sprintf(
                'jsonb_set(%s, %s, COALESCE((%s)#>%s, \'{}\'::jsonb), true)',
                $expression,
                $pathLiteral,
                $expression,
                $pathLiteral,
            );
        }

        return $expression;
    }
}
