<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use Countable;
use IteratorAggregate;
use JsonException;
use Patchlevel\Rango\Exception\DecodeException;
use PDO;
use PDOStatement;
use Traversable;

use function array_map;
use function count;
use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * @template TDocument of array<string, mixed>
 * @implements IteratorAggregate<int, TDocument>
 */
final readonly class Cursor implements IteratorAggregate, Countable
{
    /** @param list<string>|PDOStatement $data */
    public function __construct(
        private array|PDOStatement $data = [],
    ) {
    }

    /** @return Traversable<int, TDocument> */
    public function getIterator(): Traversable
    {
        if ($this->data instanceof PDOStatement) {
            while (true) {
                $item = $this->data->fetchColumn();
                if ($item === false) {
                    break;
                }

                yield $this->decode((string)$item);
            }

            return;
        }

        foreach ($this->data as $item) {
            yield $this->decode($item);
        }
    }

    public function count(): int
    {
        if ($this->data instanceof PDOStatement) {
            return $this->data->rowCount();
        }

        return count($this->data);
    }

    /** @return list<TDocument> */
    public function toArray(): array
    {
        if ($this->data instanceof PDOStatement) {
            return array_map(
                fn ($item) => $this->decode(is_string($item) ? $item : ''),
                $this->data->fetchAll(PDO::FETCH_COLUMN),
            );
        }

        return array_map(
            fn (string $item) => $this->decode($item),
            $this->data,
        );
    }

    /** @return array<string, mixed> */
    private function decode(string $json): array
    {
        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DecodeException($json, $e->getMessage(), (int)$e->getCode(), $e);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
