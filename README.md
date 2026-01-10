# Rango

[![Latest Version on Packagist](https://img.shields.io/packagist/v/patchlevel/rango.svg?style=flat-square)](https://packagist.org/packages/patchlevel/rango)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/patchlevel/rango/unit.yml?branch=main&label=tests&style=flat-square)](https://github.com/patchlevel/rango/actions)
[![License](https://img.shields.io/packagist/l/patchlevel/rango.svg?style=flat-square)](https://packagist.org/packages/patchlevel/rango)

Rango is a PHP library that reimplements the **MongoDB PHP API** on top of **PostgreSQL** using JSONB.

It aims to provide a drop-in–compatible API, allowing applications to interact with PostgreSQL using familiar MongoDB-style operations. By mirroring the MongoDB PHP interface, Rango makes it possible to switch between PostgreSQL and a real MongoDB backend with minimal or no code changes.

## Why Rango?

- **JSONB Power**: Leverage PostgreSQL's robust JSONB support with a MongoDB-like interface.
- **Easy Migration**: Use MongoDB-style queries while staying within your existing PostgreSQL infrastructure.
- **Compatibility**: Designed to be a drop-in replacement for `mongodb/mongodb` in many scenarios.

## Installation

```bash
composer require patchlevel/rango
```

## Quick Start

```php
use Patchlevel\Rango\Client;

// Connect to PostgreSQL
$client = new Client('pgsql:host=localhost;port=5432;dbname=app;user=postgres;password=postgres');

$collection = $client->selectDatabase('test')->selectCollection('users');

// Insert a document
$collection->insertOne([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'tags' => ['php', 'postgres']
]);

// Find documents
$users = $collection->find(['tags' => 'php']);

foreach ($users as $user) {
    echo $user['name'] . "\n";
}
```

## Supported Features

### CRUD Operations
| Feature | Supported Methods |
| --- | --- |
| **Create** | `insertOne`, `insertMany` |
| **Read** | `find`, `findOne`, `countDocuments`, `distinct` |
| **Update** | `updateOne`, `updateMany`, `replaceOne` |
| **Delete** | `deleteOne`, `deleteMany` |
| **Find & Modify** | `findOneAndUpdate`, `findOneAndReplace`, `findOneAndDelete` |

### Query Operators
*   **Comparison**: `$eq`, `$ne`, `$gt`, `$gte`, `$lt`, `$lte`, `$in`, `$nin`
*   **Logical**: `$and`, `$or`, `$nor`, `$not`
*   **Element**: `$exists`
*   **Evaluation**: `$regex` (with `$options => 'i'`)
*   **Array**: `$all`, `$size`

### Aggregation Framework
*   **Stages**: `$match`, `$sort`, `$limit`, `$skip`, `$project`, `$unwind`, `$group`
*   **Accumulators**: `$sum` (within `$group`)

### Management
*   **Index Management**: `createIndex`, `dropIndex`, `listIndexes`
*   **Database & Collection**: `listDatabases`, `listCollections`, `renameCollection`, `drop`

## Development

### Prerequisites

- PHP 8.3+
- Docker & Docker Compose

### Running Tests

To run the integration tests locally, Postgres and MongoDB must be running. A Docker Compose configuration is provided:

```bash
docker compose up -d
vendor/bin/phpunit
```