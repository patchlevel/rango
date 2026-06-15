# Connection

The `Client` is the entry point to Rango. It owns the PostgreSQL connection and hands out `Database` and [Collection](crud-operations.md) objects that you use for everything else. The same client also lets you inspect and manage the databases and collections themselves.

## Creating a client

The simplest way to connect is with a PDO DSN string. Rango opens the connection and configures it to throw exceptions on errors:

```php
use Patchlevel\Rango\Client;

$client = new Client('pgsql:host=localhost;port=5432;dbname=app;user=postgres;password=postgres');
```
## Reusing an existing PDO

If your application already manages a `PDO` instance, for example through a dependency injection container, you can pass it directly. Rango then uses your connection instead of opening its own:

```php
use Patchlevel\Rango\Client;

$pdo = new PDO('pgsql:host=localhost;dbname=app', 'postgres', 'postgres', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$client = new Client($pdo);
```
:::tip
Sharing a single `PDO` instance lets Rango operations take part in the same transaction as the rest of your application.
:::

## Selecting databases and collections

A `Client` gives you a `Database`, and a `Database` gives you a `Collection`. The `getXxx` and `selectXxx` methods are equivalent, so use whichever reads better:

```php
$database = $client->selectDatabase('app');
$collection = $database->selectCollection('users');

// or in one step
$collection = $client->selectCollection('app', 'users');
```
Nothing is queried while selecting. The schema and table are created lazily the first time you write to the collection, as explained in [how it works](how-it-works.md).

## Listing databases

`listDatabases` returns an iterator of `DatabaseInfo` objects, one per PostgreSQL schema:

```php
foreach ($client->listDatabases() as $database) {
    echo $database->getName();
}
```
## Listing collections

`listCollections` returns the collections in a database as `CollectionInfo` objects. Call it on the client with a database name, or on a `Database`:

```php
foreach ($client->listCollections('app') as $collection) {
    echo $collection->getName();
}

$database = $client->selectDatabase('app');
foreach ($database->listCollections() as $collection) {
    echo $collection->getName();
}
```
## Renaming a collection

`renameCollection` changes a collection's name within its database:

```php
$database = $client->selectDatabase('app');
$database->renameCollection('users', 'members');
```
## Dropping collections and databases

Drop a single collection from its database, or drop the whole database with all of its collections:

```php
$database = $client->selectDatabase('app');

$database->dropCollection('members');

// or drop the collection through its own handle
$client->selectCollection('app', 'members')->drop();

// remove the entire database (schema)
$database->drop();
```
:::danger
Dropping a collection or database is irreversible and removes all of its documents. The database drop cascades to every collection it contains.
:::

## Learn more

* [How to run CRUD operations on a collection](crud-operations.md)
* [How to query and shape results](querying.md)
* [How Rango maps a connection to PostgreSQL schemas](how-it-works.md)
