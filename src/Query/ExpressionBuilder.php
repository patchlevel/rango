<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Query;

use InvalidArgumentException;
use PDO;

use function array_is_list;
use function array_key_exists;
use function array_key_first;
use function array_map;
use function count;
use function implode;
use function is_array;
use function is_string;
use function json_encode;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtr;
use function substr;

/**
 * Compiles a MongoDB aggregation expression into an SQL fragment that evaluates
 * to a jsonb value. The current row is referenced through the `data` column.
 */
final readonly class ExpressionBuilder
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function compile(mixed $expression): string
    {
        if (is_string($expression)) {
            if (str_starts_with($expression, '$$')) {
                return $this->systemVariable($expression);
            }

            if (str_starts_with($expression, '$')) {
                return $this->fieldReference(substr($expression, 1));
            }

            return $this->literal($expression);
        }

        if (!is_array($expression)) {
            return $this->literal($expression);
        }

        if ($expression === [] || array_is_list($expression)) {
            $items = array_map(fn (mixed $item): string => $this->compile($item), $expression);

            return sprintf('jsonb_build_array(%s)', implode(', ', $items));
        }

        $firstKey = array_key_first($expression);
        if (is_string($firstKey) && str_starts_with($firstKey, '$')) {
            if (count($expression) !== 1) {
                throw new InvalidArgumentException(
                    sprintf('Expression object for "%s" must contain exactly one operator', $firstKey),
                );
            }

            return $this->operator($firstKey, $expression[$firstKey]);
        }

        $parts = [];
        foreach ($expression as $key => $value) {
            $parts[] = $this->pdo->quote((string)$key);
            $parts[] = $this->compile($value);
        }

        return sprintf('jsonb_build_object(%s)', implode(', ', $parts));
    }

    /** Compile an expression into an SQL boolean using MongoDB truthiness rules. */
    public function compileBoolean(mixed $expression): string
    {
        $sql = $this->compile($expression);

        return sprintf(
            '(%1$s IS NOT NULL AND %1$s <> \'false\'::jsonb AND %1$s <> \'null\'::jsonb AND %1$s <> \'0\'::jsonb)',
            $sql,
        );
    }

    /** Compile an expression into a numeric SQL value. */
    public function compileNumeric(mixed $expression): string
    {
        return sprintf('(%s #>> \'{}\')::numeric', $this->compile($expression));
    }

    private function operator(string $operator, mixed $argument): string
    {
        return match ($operator) {
            '$literal' => $this->literal($argument),

            '$concat' => $this->concat($this->operands($argument)),
            '$toUpper' => sprintf('to_jsonb(upper(coalesce(%s, \'\')))', $this->text($argument)),
            '$toLower' => sprintf('to_jsonb(lower(coalesce(%s, \'\')))', $this->text($argument)),
            '$substr', '$substrCP', '$substrBytes' => $this->substr($this->operands($argument)),
            '$strLenCP', '$strLenBytes' => sprintf('to_jsonb(length(coalesce(%s, \'\')))', $this->text($argument)),
            '$toString' => sprintf('to_jsonb(%s)', $this->text($argument)),
            '$toInt', '$toLong' => sprintf('to_jsonb((%s)::bigint)', $this->text($argument)),
            '$toDouble', '$toDecimal' => sprintf('to_jsonb(%s)', $this->compileNumeric($argument)),
            '$toBool' => sprintf('to_jsonb(%s)', $this->compileBoolean($argument)),

            '$add' => $this->arithmetic('+', $this->operands($argument)),
            '$subtract' => $this->arithmetic('-', $this->operands($argument)),
            '$multiply' => $this->arithmetic('*', $this->operands($argument)),
            '$divide' => $this->arithmetic('/', $this->operands($argument)),
            '$mod' => $this->modulo($this->operands($argument)),
            '$abs' => sprintf('to_jsonb(trim_scale(abs(%s)))', $this->compileNumeric($argument)),
            '$ceil' => sprintf('to_jsonb(ceil(%s))', $this->compileNumeric($argument)),
            '$floor' => sprintf('to_jsonb(floor(%s))', $this->compileNumeric($argument)),
            '$round' => $this->round($this->operands($argument)),

            '$eq' => $this->comparison('IS NOT DISTINCT FROM', $this->operands($argument)),
            '$ne' => $this->comparison('IS DISTINCT FROM', $this->operands($argument)),
            '$gt' => $this->comparison('>', $this->operands($argument)),
            '$gte' => $this->comparison('>=', $this->operands($argument)),
            '$lt' => $this->comparison('<', $this->operands($argument)),
            '$lte' => $this->comparison('<=', $this->operands($argument)),

            '$and' => $this->logical('AND', $this->operands($argument)),
            '$or' => $this->logical('OR', $this->operands($argument)),
            '$not' => sprintf('to_jsonb(NOT %s)', $this->compileBoolean($this->firstOperand($argument))),

            '$ifNull' => $this->ifNull($this->operands($argument)),
            '$cond' => $this->cond($argument),
            '$switch' => $this->switchExpression($argument),

            '$year' => $this->datePart('year', $argument),
            '$month' => $this->datePart('month', $argument),
            '$dayOfMonth' => $this->datePart('day', $argument),
            '$hour' => $this->datePart('hour', $argument),
            '$minute' => $this->datePart('minute', $argument),
            '$second' => $this->datePart('second', $argument),
            '$dateToString' => $this->dateToString($argument),

            '$size' => sprintf(
                'to_jsonb(CASE WHEN jsonb_typeof(%1$s) = \'array\' THEN jsonb_array_length(%1$s) END)',
                $this->compile($this->firstOperand($argument)),
            ),
            '$isArray' => sprintf(
                'to_jsonb(COALESCE(jsonb_typeof(%s) = \'array\', false))',
                $this->compile($this->firstOperand($argument)),
            ),
            '$arrayElemAt' => $this->arrayElemAt($this->operands($argument)),
            '$first' => sprintf('(%s) -> 0', $this->compile($this->firstOperand($argument))),
            '$last' => sprintf('(%s) -> -1', $this->compile($this->firstOperand($argument))),
            '$in' => $this->inArray($this->operands($argument)),
            '$concatArrays' => $this->concatArrays($this->operands($argument)),
            '$reverseArray' => $this->reverseArray($this->firstOperand($argument)),
            '$slice' => $this->slice($this->operands($argument)),

            default => throw new InvalidArgumentException(
                sprintf('Unsupported aggregation expression operator "%s"', $operator),
            ),
        };
    }

    /** @return list<mixed> */
    private function operands(mixed $argument): array
    {
        return is_array($argument) && array_is_list($argument) ? $argument : [$argument];
    }

    private function firstOperand(mixed $argument): mixed
    {
        return $this->operands($argument)[0] ?? null;
    }

    private function literal(mixed $value): string
    {
        return sprintf('%s::jsonb', $this->pdo->quote((string)json_encode($value)));
    }

    private function systemVariable(string $expression): string
    {
        return match ($expression) {
            '$$ROOT', '$$CURRENT' => 'data',
            '$$NOW' => 'to_jsonb(now())',
            default => throw new InvalidArgumentException(
                sprintf('Unsupported system variable "%s"', $expression),
            ),
        };
    }

    private function fieldReference(string $path): string
    {
        if ($path === '') {
            return 'data';
        }

        if (!str_contains($path, '.')) {
            return sprintf('data->%s', $this->pdo->quote($path));
        }

        return sprintf('data#>%s', $this->pdo->quote('{' . str_replace('.', ',', $path) . '}'));
    }

    private function text(mixed $expression): string
    {
        return sprintf('(%s #>> \'{}\')', $this->compile($expression));
    }

    /** @param list<mixed> $operands */
    private function concat(array $operands): string
    {
        $parts = array_map(fn (mixed $operand): string => $this->text($operand), $operands);

        return sprintf('to_jsonb(%s)', implode(' || ', $parts));
    }

    /** @param list<mixed> $operands */
    private function substr(array $operands): string
    {
        $string = $this->text($operands[0] ?? null);
        $start = sprintf('(%s)::int', $this->compileNumeric($operands[1] ?? 0));
        $length = sprintf('(%s)::int', $this->compileNumeric($operands[2] ?? -1));

        return sprintf(
            'to_jsonb(CASE WHEN %3$s < 0 THEN substr(%1$s, %2$s + 1) ELSE substr(%1$s, %2$s + 1, %3$s) END)',
            $string,
            $start,
            $length,
        );
    }

    /** @param list<mixed> $operands */
    private function arithmetic(string $operator, array $operands): string
    {
        $parts = array_map(fn (mixed $operand): string => $this->compileNumeric($operand), $operands);

        return sprintf('to_jsonb(trim_scale(%s))', implode(sprintf(' %s ', $operator), $parts));
    }

    /** @param list<mixed> $operands */
    private function modulo(array $operands): string
    {
        return sprintf(
            'to_jsonb(trim_scale(mod(%s, %s)))',
            $this->compileNumeric($operands[0] ?? 0),
            $this->compileNumeric($operands[1] ?? 1),
        );
    }

    /** @param list<mixed> $operands */
    private function round(array $operands): string
    {
        $place = isset($operands[1]) ? sprintf('(%s)::int', $this->compileNumeric($operands[1])) : '0';

        return sprintf('to_jsonb(trim_scale(round(%s, %s)))', $this->compileNumeric($operands[0] ?? 0), $place);
    }

    /** @param list<mixed> $operands */
    private function comparison(string $operator, array $operands): string
    {
        return sprintf(
            'to_jsonb((%s) %s (%s))',
            $this->compile($operands[0] ?? null),
            $operator,
            $this->compile($operands[1] ?? null),
        );
    }

    /** @param list<mixed> $operands */
    private function logical(string $operator, array $operands): string
    {
        if ($operands === []) {
            return $operator === 'AND' ? "'true'::jsonb" : "'false'::jsonb";
        }

        $parts = array_map(fn (mixed $operand): string => $this->compileBoolean($operand), $operands);

        return sprintf('to_jsonb(%s)', implode(sprintf(' %s ', $operator), $parts));
    }

    /** @param list<mixed> $operands */
    private function ifNull(array $operands): string
    {
        $parts = array_map(fn (mixed $operand): string => $this->compile($operand), $operands);

        return sprintf('COALESCE(%s)', implode(', ', $parts));
    }

    private function cond(mixed $argument): string
    {
        if (is_array($argument) && array_is_list($argument)) {
            [$if, $then, $else] = [$argument[0] ?? null, $argument[1] ?? null, $argument[2] ?? null];
        } elseif (is_array($argument)) {
            [$if, $then, $else] = [$argument['if'] ?? null, $argument['then'] ?? null, $argument['else'] ?? null];
        } else {
            throw new InvalidArgumentException('$cond expects an array or an object with if/then/else');
        }

        return sprintf(
            'CASE WHEN %s THEN %s ELSE %s END',
            $this->compileBoolean($if),
            $this->compile($then),
            $this->compile($else),
        );
    }

    private function switchExpression(mixed $argument): string
    {
        if (!is_array($argument)) {
            throw new InvalidArgumentException('$switch expects an object');
        }

        $branches = $argument['branches'] ?? [];
        $default = array_key_exists('default', $argument)
            ? $this->compile($argument['default'])
            : "'null'::jsonb";

        if (!is_array($branches) || $branches === []) {
            return $default;
        }

        $cases = [];
        foreach ($branches as $branch) {
            if (!is_array($branch)) {
                throw new InvalidArgumentException('$switch branch must be an object');
            }

            $cases[] = sprintf(
                'WHEN %s THEN %s',
                $this->compileBoolean($branch['case'] ?? null),
                $this->compile($branch['then'] ?? null),
            );
        }

        return sprintf('CASE %s ELSE %s END', implode(' ', $cases), $default);
    }

    private function datePart(string $part, mixed $argument): string
    {
        $date = $argument;
        if (is_array($argument) && !array_is_list($argument) && array_key_exists('date', $argument)) {
            $date = $argument['date'];
        }

        return sprintf('to_jsonb(extract(%s from (%s)::timestamptz)::int)', $part, $this->text($date));
    }

    private function dateToString(mixed $argument): string
    {
        if (!is_array($argument)) {
            throw new InvalidArgumentException('$dateToString expects an object');
        }

        $format = $argument['format'] ?? '%Y-%m-%dT%H:%M:%S';
        $pgFormat = strtr(is_string($format) ? $format : '%Y-%m-%dT%H:%M:%S', [
            '%Y' => 'YYYY',
            '%m' => 'MM',
            '%d' => 'DD',
            '%H' => 'HH24',
            '%M' => 'MI',
            '%S' => 'SS',
            '%L' => 'MS',
            '%j' => 'DDD',
            '%%' => '%',
        ]);

        return sprintf(
            'to_jsonb(to_char((%s)::timestamptz, %s))',
            $this->text($argument['date'] ?? null),
            $this->pdo->quote($pgFormat),
        );
    }

    /** @param list<mixed> $operands */
    private function arrayElemAt(array $operands): string
    {
        return sprintf(
            '(%s) -> %s',
            $this->compile($operands[0] ?? null),
            $this->intExpression($operands[1] ?? 0),
        );
    }

    /** @param list<mixed> $operands */
    private function inArray(array $operands): string
    {
        return sprintf(
            'to_jsonb(EXISTS (SELECT 1 FROM jsonb_array_elements(%s) AS __in(__v) WHERE __in.__v = %s))',
            $this->arrayCase($operands[1] ?? null),
            $this->compile($operands[0] ?? null),
        );
    }

    /** @param list<mixed> $operands */
    private function concatArrays(array $operands): string
    {
        if ($operands === []) {
            return "'[]'::jsonb";
        }

        $parts = array_map(fn (mixed $operand): string => sprintf('(%s)', $this->compile($operand)), $operands);

        return implode(' || ', $parts);
    }

    private function reverseArray(mixed $argument): string
    {
        $sql = $this->compile($argument);

        return sprintf(
            'CASE WHEN jsonb_typeof(%1$s) = \'array\' THEN ('
            . 'SELECT COALESCE(jsonb_agg(__r.__v ORDER BY __r.__ord DESC), \'[]\'::jsonb)'
            . ' FROM jsonb_array_elements(%1$s) WITH ORDINALITY AS __r(__v, __ord)) END',
            $sql,
        );
    }

    /** @param list<mixed> $operands */
    private function slice(array $operands): string
    {
        $array = $this->arrayCase($operands[0] ?? null);

        if (array_key_exists(2, $operands)) {
            $position = $this->intExpression($operands[1] ?? 0);
            $count = $this->intExpression($operands[2] ?? 0);
            $condition = sprintf(
                'CASE WHEN %1$s >= 0 THEN q.__ord > %1$s AND q.__ord <= %1$s + %2$s'
                . ' ELSE q.__ord > q.__total + %1$s AND q.__ord <= q.__total + %1$s + %2$s END',
                $position,
                $count,
            );
        } else {
            $count = $this->intExpression($operands[1] ?? 0);
            $condition = sprintf(
                'CASE WHEN %1$s >= 0 THEN q.__ord <= %1$s ELSE q.__ord > q.__total + %1$s END',
                $count,
            );
        }

        return sprintf(
            '(SELECT COALESCE(jsonb_agg(q.__v ORDER BY q.__ord), \'[]\'::jsonb) FROM ('
            . 'SELECT __s.__v AS __v, __s.__ord AS __ord, count(*) OVER () AS __total'
            . ' FROM jsonb_array_elements(%s) WITH ORDINALITY AS __s(__v, __ord)) q WHERE %s)',
            $array,
            $condition,
        );
    }

    private function arrayCase(mixed $expression): string
    {
        $sql = $this->compile($expression);

        return sprintf(
            'CASE WHEN jsonb_typeof(%1$s) = \'array\' THEN %1$s ELSE \'[]\'::jsonb END',
            $sql,
        );
    }

    private function intExpression(mixed $expression): string
    {
        return sprintf('(%s)::int', $this->compileNumeric($expression));
    }
}
