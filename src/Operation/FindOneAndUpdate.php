<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\Cursor;
use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use PDO;

use function is_string;

/** @extends CollectionOperation<Cursor|null> */
final class FindOneAndUpdate extends CollectionOperation
{
    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $database,
        string $collection,
        private readonly array $filter,
        private readonly array $update,
        private readonly array $options = [],
    ) {
        parent::__construct($database, $collection);
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): Cursor|null
    {
        $sql = $queryBuilder->createSelect($this->database, $this->collection, $this->filter, ['limit' => 1]);
        $statement = SqlRunner::query($pdo, $sql);

        $data = $statement->fetchColumn();

        if (!is_string($data)) {
            return null;
        }

        $sql = $queryBuilder->createUpdate($this->database, $this->collection, $this->filter, $this->update, $this->options, false);
        SqlRunner::exec($pdo, $sql);

        return new Cursor([$data]);
    }
}
