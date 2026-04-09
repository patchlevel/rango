<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use Iterator;
use Patchlevel\Rango\Model\CollectionInfo;
use Patchlevel\Rango\Model\DatabaseInfo;
use Patchlevel\Rango\Sql\Identifier;
use PDO;

use function sprintf;

final class Client
{
    private readonly PDO $pdo;
    private readonly QueryBuilder $queryBuilder;

    public function __construct(string|PDO $uri)
    {
        if ($uri instanceof PDO) {
            $this->pdo = $uri;
        } else {
            $this->pdo = new PDO($uri, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        }

        $this->queryBuilder = new QueryBuilder($this->pdo);
    }

    public function getDatabase(string $name): Database
    {
        return new Database($this, $name);
    }

    public function selectDatabase(string $name): Database
    {
        return $this->getDatabase($name);
    }

    public function getCollection(string $databaseName, string $collectionName): Collection
    {
        return $this->getDatabase($databaseName)->getCollection($collectionName);
    }

    /** @return Collection<array<string, mixed>> */
    public function selectCollection(string $databaseName, string $collectionName): Collection
    {
        return $this->getCollection($databaseName, $collectionName);
    }

    public function dropDatabase(string $name): void
    {
        SqlRunner::exec($this->pdo, sprintf('DROP SCHEMA IF EXISTS %s CASCADE', Identifier::quote($name)));
    }

    /** @return Iterator<DatabaseInfo> */
    public function listDatabases(): Iterator
    {
        return $this->run(new Operation\ListDatabases());
    }

    /** @return Iterator<CollectionInfo> */
    public function listCollections(string $database): Iterator
    {
        return $this->run(new Operation\ListCollections($database));
    }

    public function renameCollection(string $database, string $oldName, string $newName): void
    {
        $this->run(new Operation\RenameCollection($database, $oldName, $newName));
    }

    /**
     * @param Operation\Operation<TReturn> $operation
     *
     * @return TReturn
     *
     * @template TReturn
     */
    public function run(Operation\Operation $operation): mixed
    {
        if ($operation instanceof Operation\CollectionOperation) {
            $this->run(new Operation\CreateCollection($operation->database, $operation->collection));
        }

        return $operation->execute($this->pdo, $this->queryBuilder);
    }
}
