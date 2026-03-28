<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\Model\Cursor;
use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use PDO;

use function is_string;

/** @extends CollectionOperation<Cursor|null> */
final class FindOneAndReplace extends CollectionOperation
{
    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $replacement
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $database,
        string $collection,
        private readonly array $filter,
        private readonly array $replacement,
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

        $sql = $queryBuilder->createReplace($this->database, $this->collection, $this->filter, $this->replacement, $this->options);
        SqlRunner::exec($pdo, $sql);

        return new Cursor([$data]);
    }
}
