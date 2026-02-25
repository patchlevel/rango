<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

/**
 * @template TReturn
 * @implements Operation<TReturn>
 */
abstract class CollectionOperation implements Operation
{
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
    ) {
    }
}
