<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use Countable;
use IteratorAggregate;
use PDO;
use PDOStatement;
use Traversable;

use function array_map;
use function count;
use function is_array;
use function is_string;
use function json_decode;

/** @implements IteratorAggregate<int, array<string, mixed>> */
class Cursor implements IteratorAggregate, Countable
{
    /** @param list<string>|PDOStatement $data */
    public function __construct(
        private readonly array|PDOStatement $data = [],
    ) {
    }

    /** @return Traversable<int, array<string, mixed>> */
    public function getIterator(): Traversable
    {
        if ($this->data instanceof PDOStatement) {
            while (true) {
                $item = $this->data->fetchColumn();
                if ($item === false) {
                    break;
                }

                yield $this->decode((string) $item);
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

    /** @return array<int|string, array<string, mixed>> */
    public function toArray(): array
    {
        if ($this->data instanceof PDOStatement) {
            $data = array_map(
                fn ($item) => $this->decode(is_string($item) ? $item : ''),
                $this->data->fetchAll(PDO::FETCH_COLUMN),
            );

            return $data;
        }

        $data = array_map(
            fn (string $item) => $this->decode($item),
            $this->data,
        );

        return $data;
    }

    /** @return array<string, mixed> */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
