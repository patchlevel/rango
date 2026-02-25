<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\Sql\Identifier;
use Patchlevel\Rango\SqlRunner;
use PDO;

use function sprintf;

/** @implements Operation<bool> */
final class CreateCollection implements Operation
{
    public function __construct(
        private readonly string $database,
        private readonly string $collection,
    ) {
    }

    public function execute(PDO $pdo, QueryBuilder $queryBuilder): bool
    {
        $schema = Identifier::quote($this->database);
        $table = Identifier::quote($this->collection);
        SqlRunner::exec($pdo, sprintf('CREATE TABLE IF NOT EXISTS %s.%s (data JSONB NOT NULL)', $schema, $table));
        SqlRunner::exec($pdo, sprintf(
            "CREATE UNIQUE INDEX IF NOT EXISTS %s ON %s.%s ((data->>'_id'))",
            Identifier::quote($this->database . '_' . $this->collection . '_id_idx'),
            $schema,
            $table,
        ));

        return true;
    }
}
