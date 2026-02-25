<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\Cursor;
use Patchlevel\Rango\QueryBuilder;
use PDO;

use function is_string;

/** @extends CollectionOperation<Cursor|null> */
final class FindOneAndDelete extends CollectionOperation
{
    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $database,
        string $collection,
        private readonly array $filter,
        /** @phpstan-ignore-next-line */
        private readonly array $options = [],
    ) {
        parent::__construct($database, $collection);
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): Cursor|null
    {
        $sql = $queryBuilder->createSelect($this->database, $this->collection, $this->filter, ['limit' => 1]);
        $statement = $pdo->query($sql);
        if ($statement === false) {
            return null;
        }

        $data = $statement->fetchColumn();

        if (!is_string($data)) {
            return null;
        }

        $sql = $queryBuilder->createDelete($this->database, $this->collection, $this->filter, false);
        $pdo->exec($sql);

        return new Cursor([$data]);
    }
}
