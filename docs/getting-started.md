# Getting Started

This tutorial walks you through Rango by building a small example: an `app` database with a `users` collection. You connect to PostgreSQL, insert documents, query them with MongoDB-style filters, and update them atomically.

By the end you will know how to open a connection, run the core [CRUD operations](crud-operations.md), and where to go next for advanced features.

## Installation

Install Rango with Composer:

```bash
composer require patchlevel/rango
```
You need a running PostgreSQL instance. The fastest way to get one locally is Docker:

```bash
docker run --rm -e POSTGRES_PASSWORD=postgres -p 5432:5432 postgres:16-alpine
```
## Connect to PostgreSQL

A [Client](connection.md) is the entry point. It takes a standard PDO DSN and manages the underlying connection:

```php
use Patchlevel\Rango\Client;

$client = new Client('pgsql:host=localhost;port=5432;dbname=app;user=postgres;password=postgres');
```
:::note
You can also pass an existing `PDO` instance instead of a DSN string. See the [connection](connection.md) page for details.
:::

## Select a collection

In Rango, a database maps to a PostgreSQL schema and a collection maps to a table. You select them by name, and they are created automatically on the first write:

```php
$collection = $client->selectDatabase('app')->selectCollection('users');
```
:::success
You never run migrations by hand. Rango creates the schema and table the first time you write to a collection.
:::

## Insert some documents

Documents are plain PHP arrays. If you omit the `_id`, Rango generates one for you:

```php
$collection->insertOne([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 27,
    'tags' => ['php', 'postgres'],
    'profile' => ['stats' => ['score' => 42]],
]);

$result = $collection->insertMany([
    ['name' => 'Jane Roe', 'email' => 'jane@example.com', 'age' => 31, 'tags' => ['php']],
    ['name' => 'Max Mustermann', 'email' => 'max@example.com', 'age' => 19, 'tags' => ['mongodb']],
]);

echo $result->getInsertedCount(); // 2
```
## Find documents

Pass a MongoDB-style filter to `find`. Here you combine an array match with a [comparison operator](querying.md#comparison-operators):

```php
$users = $collection->find([
    'tags' => 'php',
    'age' => ['$gte' => 25],
]);

foreach ($users as $user) {
    echo $user['name'] . "\n";
}
```
:::note
`find` returns a [Cursor](crud-operations.md), which you can iterate directly or turn into an array with `toArray()`.
:::

To fetch a single document, use `findOne`:

```php
$user = $collection->findOne(['email' => 'john@example.com']);
```
## Update documents

Updates use [update operators](update-operators.md). This increments a nested counter and adds a tag atomically:

```php
$collection->updateOne(
    ['email' => 'john@example.com'],
    [
        '$inc' => ['profile.stats.score' => 1],
        '$push' => ['tags' => 'mongodb'],
    ],
);
```
## Delete documents

Deleting works the same way, with a filter:

```php
$collection->deleteOne(['email' => 'max@example.com']);
```
## Result

You now have a working Rango setup: a client connected to PostgreSQL, a collection that stores documents as `JSONB`, and the full CRUD cycle running through the MongoDB-style API. Everything you wrote lives in a regular PostgreSQL table you can back up, replicate, and query with SQL.

## Learn more

* [How to connect and configure the client](connection.md)
* [How to query with operators](querying.md)
* [How to build aggregation pipelines](aggregation.md)
* [How Rango maps documents to PostgreSQL](how-it-works.md)
