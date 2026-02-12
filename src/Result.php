<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use Countable;
use IteratorAggregate;
use PDOStatement;
use Traversable;

use function array_map;
use function count;
use function json_decode;

/**
 * @template T
 * @implements IteratorAggregate<int, T>
 */
class Result implements IteratorAggregate, Countable
{
    /** @param list<string>|PDOStatement $data */
    public function __construct(
        private readonly array|PDOStatement $data = [],
    ) {
    }

    public function getInsertedId(): string|null
    {
        if ($this->data instanceof PDOStatement) {
            return null;
        }

        if (count($this->data) === 0) {
            return null;
        }

        $first = json_decode($this->data[0], true);

        return $first['_id'] ?? null;
    }

    /** @return Traversable<int, array<string, mixed>> */
    public function getIterator(): Traversable
    {
        if ($this->data instanceof PDOStatement) {
            while ($item = $this->data->fetchColumn()) {
                yield json_decode($item, true);
            }

            return;
        }

        foreach ($this->data as $item) {
            yield json_decode($item, true);
        }
    }

    public function count(): int
    {
        if ($this->data instanceof PDOStatement) {
            return $this->data->rowCount();
        }

        return count($this->data);
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        if ($this->data instanceof PDOStatement) {
            return array_map(
                static fn (string $item) => json_decode($item, true),
                $this->data->fetchAll(PDO::FETCH_COLUMN),
            );
        }

        return array_map(
            static fn (string $item) => json_decode($item, true),
            $this->data,
        );
    }
}
