<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use PDO;

use function sprintf;

final class DropCollection implements Operation
{
    public function __construct(
        private readonly string $database,
        private readonly string $collection,
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): bool
    {
        $pdo->exec(sprintf('DROP TABLE IF EXISTS %s.%s', $this->database, $this->collection));

        return true;
    }
}
