<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Model;

use ArrayAccess;
use BadMethodCallException;

use function array_key_exists;

final readonly class IndexInfo implements ArrayAccess
{
    /** @param array{name: string, key: array<string, int>, unique: bool, v: int} $info */
    public function __construct(
        private array $info,
    ) {
    }

    public function __toString(): string
    {
        return $this->info['name'];
    }

    /** @return array<string, int> */
    public function getKey(): array
    {
        return $this->info['key'];
    }

    public function getName(): string
    {
        return $this->info['name'];
    }

    public function getVersion(): int
    {
        return $this->info['v'];
    }

    public function is2dSphere(): bool
    {
        return false;
    }

    public function isSparse(): bool
    {
        return false;
    }

    public function isText(): bool
    {
        return false;
    }

    public function isTtl(): bool
    {
        return false;
    }

    public function isUnique(): bool
    {
        return $this->info['unique'] ?? false;
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
