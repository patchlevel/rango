<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\BulkWriteResult;
use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use PDO;
use Throwable;

use function bin2hex;
use function random_bytes;

/** @extends CollectionOperation<BulkWriteResult> */
final class BulkWrite extends CollectionOperation
{
    /**
     * @param list<array<string, list<array<string, mixed>>>> $operations
     * @param array<string, mixed>                            $options
     */
    public function __construct(
        string $database,
        string $collection,
        private readonly array $operations,
        /** @phpstan-ignore-next-line */
        private readonly array $options = [],
    ) {
        parent::__construct($database, $collection);
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): BulkWriteResult
    {
        $insertedCount = 0;
        $matchedCount = 0;
        $modifiedCount = 0;
        $deletedCount = 0;
        $upsertedCount = 0;
        $insertedIds = [];
        $upsertedIds = [];

        $pdo->beginTransaction();

        try {
            foreach ($this->operations as $operation) {
                foreach ($operation as $type => $args) {
                    if (!isset($args[0])) {
                        continue;
                    }

                    if ($type === 'insertOne') {
                        $document = $args[0];
                        if (!isset($document['_id'])) {
                            $document['_id'] = bin2hex(random_bytes(12));
                        }

                        $sql = $queryBuilder->createInsert($this->database, $this->collection, $document);
                        SqlRunner::exec($pdo, $sql);
                        $insertedCount++;
                        $insertedIds[] = $document['_id'];
                    } elseif ($type === 'updateOne' && isset($args[1])) {
                        $sql = $queryBuilder->createUpdate($this->database, $this->collection, $args[0], $args[1], $args[2] ?? [], false);
                        $rowCount = SqlRunner::exec($pdo, $sql);
                        $matchedCount += $rowCount;
                        $modifiedCount += $rowCount;
                    } elseif ($type === 'updateMany' && isset($args[1])) {
                        $sql = $queryBuilder->createUpdate($this->database, $this->collection, $args[0], $args[1], $args[2] ?? [], true);
                        $rowCount = SqlRunner::exec($pdo, $sql);
                        $matchedCount += $rowCount;
                        $modifiedCount += $rowCount;
                    } elseif ($type === 'replaceOne' && isset($args[1])) {
                        $sql = $queryBuilder->createReplace($this->database, $this->collection, $args[0], $args[1], $args[2] ?? []);
                        $rowCount = SqlRunner::exec($pdo, $sql);
                        $matchedCount += $rowCount;
                        $modifiedCount += $rowCount;
                    } elseif ($type === 'deleteOne') {
                        $sql = $queryBuilder->createDelete($this->database, $this->collection, $args[0], false);
                        $rowCount = SqlRunner::exec($pdo, $sql);
                        $deletedCount += $rowCount;
                    } elseif ($type === 'deleteMany') {
                        $sql = $queryBuilder->createDelete($this->database, $this->collection, $args[0], true);
                        $rowCount = SqlRunner::exec($pdo, $sql);
                        $deletedCount += $rowCount;
                    }
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }

        return new BulkWriteResult(
            $insertedCount,
            $matchedCount,
            $modifiedCount,
            $deletedCount,
            $upsertedCount,
            $insertedIds,
            $upsertedIds,
        );
    }
}
