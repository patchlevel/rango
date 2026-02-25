<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\InsertOneResult;
use Patchlevel\Rango\QueryBuilder;
use PDO;

use function bin2hex;
use function random_bytes;

/** @implements Operation<InsertOneResult> */
final class InsertOne implements Operation
{
    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private array $document,
        /** @phpstan-ignore-next-line */
        private readonly array $options = [],
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): InsertOneResult
    {
        if (!isset($this->document['_id'])) {
            $this->document['_id'] = bin2hex(random_bytes(12));
        }

        $sql = $queryBuilder->createInsert($this->database, $this->collection, $this->document);
        $pdo->exec($sql);

        return new InsertOneResult($this->document['_id']);
    }
}
