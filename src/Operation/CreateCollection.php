<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use PDO;

use function sprintf;

final class CreateCollection implements Operation
{
    public function __construct(
        private readonly string $database,
        private readonly string $collection,
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): bool
    {
        $pdo->exec(sprintf('CREATE TABLE IF NOT EXISTS %s.%s (data JSONB NOT NULL)', $this->database, $this->collection));
        $pdo->exec(sprintf(
            'CREATE UNIQUE INDEX IF NOT EXISTS %s_%s_id_idx ON %s.%s ((data->>\'_id\'))',
            $this->database,
            $this->collection,
            $this->database,
            $this->collection,
        ));

        return true;
    }
}
