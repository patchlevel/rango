<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use PDO;

use function bin2hex;
use function random_bytes;

final class BulkWrite implements Operation
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $database,
        public readonly string $collection,
        private readonly array $operations,
        private readonly array $options = [],
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): null
    {
        $pdo->beginTransaction();

        try {
            foreach ($this->operations as $operation) {
                foreach ($operation as $type => $args) {
                    if ($type === 'insertOne') {
                        $document = $args[0];
                        if (!isset($document['_id'])) {
                            $document['_id'] = bin2hex(random_bytes(12));
                        }
                        $sql = $queryBuilder->createInsert($this->database, $this->collection, $document);
                        $pdo->exec($sql);
                    } elseif ($type === 'updateOne') {
                        $sql = $queryBuilder->createUpdate($this->database, $this->collection, $args[0], $args[1], $args[2] ?? [], false);
                        $pdo->exec($sql);
                    } elseif ($type === 'updateMany') {
                        $sql = $queryBuilder->createUpdate($this->database, $this->collection, $args[0], $args[1], $args[2] ?? [], true);
                        $pdo->exec($sql);
                    } elseif ($type === 'replaceOne') {
                        $sql = $queryBuilder->createReplace($this->database, $this->collection, $args[0], $args[1], $args[2] ?? []);
                        $pdo->exec($sql);
                    } elseif ($type === 'deleteOne') {
                        $sql = $queryBuilder->createDelete($this->database, $this->collection, $args[0], false);
                        $pdo->exec($sql);
                    } elseif ($type === 'deleteMany') {
                        $sql = $queryBuilder->createDelete($this->database, $this->collection, $args[0], true);
                        $pdo->exec($sql);
                    }
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return null;
    }
}
