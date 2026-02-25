<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\Sql\Identifier;
use Patchlevel\Rango\SqlRunner;
use PDO;

use function sprintf;

/** @implements Operation<bool> */
final class DropCollection implements Operation
{
    public function __construct(
        private readonly string $database,
        private readonly string $collection,
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): bool
    {
        SqlRunner::exec(
            $pdo,
            sprintf('DROP TABLE IF EXISTS %s.%s', Identifier::quote($this->database), Identifier::quote($this->collection)),
        );

        return true;
    }
}
