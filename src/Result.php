<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use Countable;
use IteratorAggregate;
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
    /** @param list<string> $data */
    public function __construct(
        private readonly array $data = [],
    ) {
    }

    public function getInsertedId(): string|null
    {
        if (count($this->data) === 0) {
            return null;
        }

        $first = json_decode($this->data[0], true);

        return $first['_id'] ?? null;
    }

    /** @return Traversable<int, array<string, mixed>> */
    public function getIterator(): Traversable
    {
        foreach ($this->data as $item) {
            yield json_decode($item, true);
        }
    }

    public function count(): int
    {
        return count($this->data);
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(
            static fn (string $item) => json_decode($item, true),
            $this->data,
        );
    }
}
