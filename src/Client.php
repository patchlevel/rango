<?php

declare(strict_types=1);

namespace Patchlevel\Rango;

use Patchlevel\Rango\Sql\Identifier;
use PDO;
use function sprintf;

final class Client
{
    private readonly PDO $pdo;
    private readonly QueryBuilder $queryBuilder;

    public function __construct(string $uri)
    {
        $this->pdo = new PDO($uri, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $this->queryBuilder = new QueryBuilder($this->pdo);
    }

    public function getDatabase(string $name): Database
    {
        SqlRunner::exec($this->pdo, sprintf('CREATE SCHEMA IF NOT EXISTS %s', Identifier::quote($name)));

        return new Database($this, $name);
    }

    public function selectDatabase(string $name): Database
    {
        return $this->getDatabase($name);
    }

    /**
     * @template TDocument of array<string, mixed>
     *
     * @return Collection<TDocument>
     */
    public function selectCollection(string $databaseName, string $collectionName): Collection
    {
        return $this->getDatabase($databaseName)->getCollection($collectionName);
    }

    public function dropDatabase(string $name): void
    {
        SqlRunner::exec($this->pdo, sprintf('DROP SCHEMA IF EXISTS %s CASCADE', Identifier::quote($name)));
    }

    /** @return list<array{name: string}> */
    public function listDatabases(): array
    {
        /** @var list<array{name: string}> $databases */
        $databases = $this->run(new Operation\ListDatabases());

        return $databases;
    }

    /** @return list<array{name: string}> */
    public function listCollections(string $database): array
    {
        /** @var list<array{name: string}> $collections */
        $collections = $this->run(new Operation\ListCollections($database));

        return $collections;
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
