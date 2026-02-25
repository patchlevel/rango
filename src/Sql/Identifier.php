<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Sql;

use function str_replace;

final class Identifier
{
    private function __construct()
    {
    }

    public static function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
