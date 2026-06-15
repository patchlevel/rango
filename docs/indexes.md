# Indexes

Indexes speed up [queries](querying.md) and [sorts](querying.md#sorting) by letting PostgreSQL find documents without scanning the whole table. Rango creates them as native PostgreSQL indexes on the `JSONB` `data` column, so you keep MongoDB-style ergonomics with PostgreSQL performance.

## Creating an index

Pass a key map to `createIndex`, where each field maps to `1` for ascending or `-1` for descending order:

```php
$collection->createIndex(['email' => 1]);

$collection->createIndex(['age' => -1, 'name' => 1]);
```
Use the `unique` option to enforce uniqueness, and `name` to choose the index name. Without a name, Rango derives one from the database, collection, and fields:

```php
$collection->createIndex(['email' => 1], ['unique' => true, 'name' => 'users_email_unique']);
```
:::tip
Index the fields you filter and sort on most often, such as the keys you pass to `find` and the `sort` option.
:::

## Listing indexes

`listIndexes` returns an iterator of `IndexInfo` objects describing the indexes on the collection:

```php
foreach ($collection->listIndexes() as $index) {
    echo $index->getName();
    $index->getKey();      // ['email' => 1]
    $index->isUnique();    // true or false
}
```
## Dropping an index

Drop an index by name:

```php
$collection->dropIndex('users_email_unique');
```
:::note
Geospatial, text, sparse, and TTL indexes are not supported. The matching `IndexInfo` checks always report `false`, as listed under [limitations](how-it-works.md#limitations).
:::

## Learn more

* [How to write the queries an index accelerates](querying.md)
* [How sorting benefits from matching indexes](querying.md#sorting)
* [How Rango maps documents and indexes onto PostgreSQL](how-it-works.md)
