<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use PDO;

final class ListCollections implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        private readonly string $databaseName,
        private readonly array $options = [],
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): array
    {
        $sql = $queryBuilder->createListCollections($this->databaseName);
        $statement = $pdo->query($sql);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
