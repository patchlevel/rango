<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Model;

use BadMethodCallException;

use function array_key_exists;

final readonly class CollectionInfo
{
    /** @param array{name: string} $info */
    public function __construct(
        private array $info,
    ) {
    }

    public function __toString(): string
    {
        return $this->info['name'];
    }

    public function getName(): string
    {
        return $this->info['name'];
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->info);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->info[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException('IndexInfo is read only');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException('IndexInfo is read only');
    }
}
