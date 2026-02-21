<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\InsertManyResult;
use Patchlevel\Rango\QueryBuilder;
use PDO;

use function bin2hex;
use function random_bytes;

final class InsertMany implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly array $documents,
        private readonly array $options = [],
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): InsertManyResult
    {
        $insertedIds = [];
        foreach ($this->documents as $document) {
            if (!isset($document['_id'])) {
                $document['_id'] = bin2hex(random_bytes(12));
            }

            $sql = $queryBuilder->createInsert($this->database, $this->collection, $document);
            $pdo->exec($sql);
            $insertedIds[] = $document['_id'];
        }

        return new InsertManyResult($insertedIds);
    }
}
