<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\Result;
use PDO;

final class FindOne implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly array $filter,
        private array $options = [],
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): Result
    {
        $this->options['limit'] = 1;
        $sql = $queryBuilder->createSelect($this->database, $this->collection, $this->filter, $this->options);
        $statement = $pdo->query($sql);
        $data = $statement->fetchColumn();

        return new Result($data ? [$data] : []);
    }
}
