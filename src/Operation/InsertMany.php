<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\InsertManyResult;
use Patchlevel\Rango\QueryBuilder;
use PDO;

use function bin2hex;
use function random_bytes;

/** @extends CollectionOperation<InsertManyResult> */
final class InsertMany extends CollectionOperation
{
    /**
     * @param list<array<string, mixed>> $documents
     * @param array<string, mixed>       $options
     */
    public function __construct(
        string $database,
        string $collection,
        private readonly array $documents,
        /** @phpstan-ignore-next-line */
        private readonly array $options = [],
    ) {
        parent::__construct($database, $collection);
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
