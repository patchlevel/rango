<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use Patchlevel\Rango\QueryBuilder;
use PDO;

interface Operation
{
    public function execute(PDO $pdo, QueryBuilder $queryBuilder): mixed;
}
