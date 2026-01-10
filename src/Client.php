<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use PDO;

use function array_map;
use function bin2hex;
use function json_decode;
use function json_encode;
use function random_bytes;
use function sprintf;

final class Client
{
    private readonly PDO $pdo;
    private readonly QueryBuilder $queryBuilder;

    public function __construct(string $uri)
    {
        $this->pdo = new PDO($uri);
        $this->queryBuilder = new QueryBuilder($this->pdo);
    }

    public function getDatabase(string $name): Database
    {
        $this->pdo->exec(sprintf('CREATE SCHEMA IF NOT EXISTS %s', $name));

        return new Database($this, $name);
    }

    public function selectDatabase(string $name): Database
    {
        return $this->getDatabase($name);
    }

    public function dropDatabase(string $name): void
    {
        $this->pdo->exec(sprintf('DROP SCHEMA IF EXISTS %s CASCADE', $name));
    }

    public function listDatabases(): array
    {
        $sql = $this->queryBuilder->createListDatabases();
        $statement = $this->pdo->query($sql);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listCollections(string $database): array
    {
        $sql = $this->queryBuilder->createListCollections($database);
        $statement = $this->pdo->query($sql);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function renameCollection(string $database, string $oldName, string $newName): void
    {
        $sql = $this->queryBuilder->createRenameCollection($database, $oldName, $newName);
        $this->pdo->exec($sql);
    }

    /** @param array<string, mixed> $arguments */
    public function execute(string $database, string $collection, string $command, array $arguments): mixed
    {
        if ($command === 'drop') {
            return $this->pdo->exec(sprintf('DROP TABLE IF EXISTS %s.%s', $database, $collection));
        }

        if ($command === 'create') {
            $this->pdo->exec(sprintf('CREATE TABLE IF NOT EXISTS %s.%s (data JSONB NOT NULL)', $database, $collection));
            $this->pdo->exec(sprintf(
                'CREATE UNIQUE INDEX IF NOT EXISTS %s_%s_id_idx ON %s.%s ((data->>\'_id\'))',
                $database,
                $collection,
                $database,
                $collection,
            ));

            return true;
        }

        if ($command === 'insertOne') {
            $this->execute($database, $collection, 'create', []);
            $document = $arguments['document'];
            if (!isset($document['_id'])) {
                $document['_id'] = bin2hex(random_bytes(12));
            }

            $sql = $this->queryBuilder->createInsert($database, $collection, $document);
            $this->pdo->exec($sql);

            return new Result([json_encode($document)]);
        }

        if ($command === 'insertMany') {
            $this->execute($database, $collection, 'create', []);
            $documents = [];
            foreach ($arguments['documents'] as $document) {
                if (!isset($document['_id'])) {
                    $document['_id'] = bin2hex(random_bytes(12));
                }

                $sql = $this->queryBuilder->createInsert($database, $collection, $document);
                $this->pdo->exec($sql);
                $documents[] = json_encode($document);
            }

            return new Result($documents);
        }

        if ($command === 'find') {
            $this->execute($database, $collection, 'create', []);
            $sql = $this->queryBuilder->createSelect($database, $collection, $arguments['filter'], $arguments['options']);
            $statement = $this->pdo->query($sql);

            return new Result($statement->fetchAll(PDO::FETCH_COLUMN));
        }

        if ($command === 'findOne') {
            $this->execute($database, $collection, 'create', []);
            $arguments['options']['limit'] = 1;
            $sql = $this->queryBuilder->createSelect($database, $collection, $arguments['filter'], $arguments['options']);
            $statement = $this->pdo->query($sql);
            $data = $statement->fetchColumn();

            return new Result($data ? [$data] : []);
        }

        if ($command === 'updateOne') {
            $this->execute($database, $collection, 'create', []);
            $sql = $this->queryBuilder->createUpdate($database, $collection, $arguments['filter'], $arguments['update'], $arguments['options'], false);
            $this->pdo->exec($sql);

            return new Result();
        }

        if ($command === 'updateMany') {
            $this->execute($database, $collection, 'create', []);
            $sql = $this->queryBuilder->createUpdate($database, $collection, $arguments['filter'], $arguments['update'], $arguments['options'], true);
            $this->pdo->exec($sql);

            return new Result();
        }

        if ($command === 'replaceOne') {
            $this->execute($database, $collection, 'create', []);
            $sql = $this->queryBuilder->createReplace($database, $collection, $arguments['filter'], $arguments['replacement'], $arguments['options']);
            $this->pdo->exec($sql);

            return new Result();
        }

        if ($command === 'deleteOne') {
            $this->execute($database, $collection, 'create', []);
            $sql = $this->queryBuilder->createDelete($database, $collection, $arguments['filter'], false);
            $this->pdo->exec($sql);

            return new Result();
        }

        if ($command === 'deleteMany') {
            $this->execute($database, $collection, 'create', []);
            $sql = $this->queryBuilder->createDelete($database, $collection, $arguments['filter'], true);
            $this->pdo->exec($sql);

            return new Result();
        }

        if ($command === 'count') {
            $this->execute($database, $collection, 'create', []);
            $sql = $this->queryBuilder->createCount($database, $collection, $arguments['filter']);
            $statement = $this->pdo->query($sql);

            return (int)$statement->fetchColumn();
        }

        if ($command === 'aggregate') {
            $this->execute($database, $collection, 'create', []);
            $sql = $this->queryBuilder->createAggregate($database, $collection, $arguments['pipeline'], $arguments['options']);
            $statement = $this->pdo->query($sql);

            return new Result($statement->fetchAll(PDO::FETCH_COLUMN));
        }

        if ($command === 'distinct') {
            $this->execute($database, $collection, 'create', []);
            $sql = $this->queryBuilder->createDistinct($database, $collection, $arguments['fieldName'], $arguments['filter']);
            $statement = $this->pdo->query($sql);

            return array_map(
                static fn (string $item) => json_decode($item, true),
                $statement->fetchAll(PDO::FETCH_COLUMN),
            );
        }

        if ($command === 'findOneAndDelete') {
            $this->execute($database, $collection, 'create', []);
            $sql = $this->queryBuilder->createSelect($database, $collection, $arguments['filter'], ['limit' => 1]);
            $statement = $this->pdo->query($sql);
            $data = $statement->fetchColumn();

            if (!$data) {
                return null;
            }

            $sql = $this->queryBuilder->createDelete($database, $collection, $arguments['filter'], false);
            // We need to be careful here to only delete the one we found if there are multiple matches
            // but for now we follow the same filter.
            $this->pdo->exec($sql);

            return new Result([$data]);
        }

        if ($command === 'findOneAndReplace') {
            $this->execute($database, $collection, 'create', []);
            $sql = $this->queryBuilder->createSelect($database, $collection, $arguments['filter'], ['limit' => 1]);
            $statement = $this->pdo->query($sql);
            $data = $statement->fetchColumn();

            if (!$data) {
                return null;
            }

            $sql = $this->queryBuilder->createReplace($database, $collection, $arguments['filter'], $arguments['replacement'], $arguments['options']);
            $this->pdo->exec($sql);

            return new Result([$data]);
        }

        if ($command === 'findOneAndUpdate') {
            $this->execute($database, $collection, 'create', []);
            $sql = $this->queryBuilder->createSelect($database, $collection, $arguments['filter'], ['limit' => 1]);
            $statement = $this->pdo->query($sql);
            $data = $statement->fetchColumn();

            if (!$data) {
                return null;
            }

            $sql = $this->queryBuilder->createUpdate($database, $collection, $arguments['filter'], $arguments['update'], $arguments['options'], false);
            $this->pdo->exec($sql);

            return new Result([$data]);
        }

        if ($command === 'createIndex') {
            $this->execute($database, $collection, 'create', []);
            $sql = $this->queryBuilder->createCreateIndex($database, $collection, $arguments['key'], $arguments['options']);
            $this->pdo->exec($sql);

            return true;
        }

        if ($command === 'dropIndex') {
            $sql = $this->queryBuilder->createDropIndex($database, $arguments['name']);
            $this->pdo->exec($sql);

            return true;
        }

        if ($command === 'listIndexes') {
            $sql = $this->queryBuilder->createListIndexes($database, $collection);
            $statement = $this->pdo->query($sql);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }

        return null;
    }
}
