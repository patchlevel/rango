<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use PDO;

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
        return $this->run(new Operation\ListDatabases());
    }

    public function listCollections(string $database): array
    {
        return $this->run(new Operation\ListCollections($database));
    }

    public function renameCollection(string $database, string $oldName, string $newName): void
    {
        $this->run(new Operation\RenameCollection($database, $oldName, $newName));
    }

    public function run(Operation\Operation $operation): mixed
    {
        if (
            $operation instanceof Operation\InsertOne ||
            $operation instanceof Operation\InsertMany ||
            $operation instanceof Operation\Find ||
            $operation instanceof Operation\FindOne ||
            $operation instanceof Operation\Update ||
            $operation instanceof Operation\ReplaceOne ||
            $operation instanceof Operation\Delete ||
            $operation instanceof Operation\Count ||
            $operation instanceof Operation\Aggregate ||
            $operation instanceof Operation\Distinct ||
            $operation instanceof Operation\FindOneAndDelete ||
            $operation instanceof Operation\FindOneAndReplace ||
            $operation instanceof Operation\FindOneAndUpdate ||
            $operation instanceof Operation\BulkWrite ||
            $operation instanceof Operation\CreateIndex
        ) {
            $this->run(new Operation\CreateCollection($operation->database, $operation->collection));
        }

        return $operation->execute($this->pdo, $this->queryBuilder);
    }
}
