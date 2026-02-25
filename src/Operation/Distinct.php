<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Operation;

use JsonException;
use Patchlevel\Rango\Exception\DecodeException;
use Patchlevel\Rango\QueryBuilder;
use Patchlevel\Rango\SqlRunner;
use PDO;

use function array_map;
use function array_values;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/** @extends CollectionOperation<list<mixed>> */
final class Distinct extends CollectionOperation
{
    /**
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $database,
        string $collection,
        private readonly string $fieldName,
        private readonly array $filter = [],
        /** @phpstan-ignore-next-line */
        private readonly array $options = [],
    ) {
        parent::__construct($database, $collection);
    }

    /** @return list<mixed> */
    public function execute(PDO $pdo, QueryBuilder $queryBuilder): array
    {
        $sql = $queryBuilder->createDistinct($this->database, $this->collection, $this->fieldName, $this->filter);
        $statement = SqlRunner::query($pdo, $sql);

        $data = array_map(
            static function ($item): mixed {
                if ($item === null) {
                    return null;
                }

                $payload = is_string($item) ? $item : '';

                try {
                    return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new DecodeException($payload, $e->getMessage(), (int)$e->getCode(), $e);
                }
            },
            $statement->fetchAll(PDO::FETCH_COLUMN),
        );

        return array_values($data);
    }
}
