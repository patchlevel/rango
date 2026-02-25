<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use PDO;

/** @template TReturn */
interface Operation
{
    /** @return TReturn */
    public function execute(PDO $pdo, QueryBuilder $queryBuilder): mixed;
}
