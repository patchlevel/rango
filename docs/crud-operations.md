# CRUD Operations

A `Collection` exposes the create, read, update, and delete methods you know from MongoDB. Documents are plain PHP arrays, and every write returns a result object that tells you what happened.

This page covers the core methods. The filter and update syntax they accept is documented under [query operators](querying.md) and [update operators](update-operators.md).

## Create

Use `insertOne` for a single document and `insertMany` for a batch. If a document has no `_id`, Rango generates one and returns it on the result:

```php
$result = $collection->insertOne([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 27,
]);

echo $result->getInsertedId();
echo $result->getInsertedCount(); // 1
```
```php
$result = $collection->insertMany([
    ['name' => 'Jane Roe', 'email' => 'jane@example.com'],
    ['name' => 'Max Mustermann', 'email' => 'max@example.com'],
]);

$result->getInsertedIds(); // [0 => '...', 1 => '...']
```
:::note
Generated ids are random 24-character hex strings. Provide your own `_id` whenever you need a stable, meaningful key.
:::

## Read

`find` returns a [Cursor](#working-with-the-cursor) over every matching document, while `findOne` returns the first match or `null`:

```php
$cursor = $collection->find(['age' => ['$gte' => 25]]);

$user = $collection->findOne(['email' => 'john@example.com']);
if ($user === null) {
    // not found
}
```
Use `countDocuments` to count matches without loading them, and `distinct` to collect the unique values of a field:

```php
$total = $collection->countDocuments(['age' => ['$gte' => 18]]);

$emails = $collection->distinct('email', ['age' => ['$gte' => 18]]);
```
### Working with the cursor

`find` returns a `Cursor`, which is iterable and countable. Iterate it directly, or materialize it with `toArray`:

```php
$cursor = $collection->find(['tags' => 'php']);

foreach ($cursor as $user) {
    echo $user['name'];
}

$users = $cursor->toArray();
$count = $cursor->count();
```
:::warning
A cursor backed by a database statement streams its rows. Iterate it once, and call `toArray()` if you need to read the result more than once.
:::

## Update

`updateOne` and `updateMany` apply [update operators](update-operators.md) to matching documents, while `replaceOne` swaps the whole document. Each returns an `UpdateResult`:

```php
$result = $collection->updateOne(
    ['email' => 'john@example.com'],
    ['$set' => ['age' => 28]],
);

echo $result->getMatchedCount();
echo $result->getModifiedCount();
```
```php
$collection->replaceOne(
    ['_id' => '1'],
    ['name' => 'John Doe', 'email' => 'john@new.example.com'],
);
```
Pass the `upsert` option to insert the document when no match exists:

```php
$collection->updateOne(
    ['_id' => 'user-42'],
    ['$set' => ['name' => 'New User']],
    ['upsert' => true],
);
```
:::warning
Upsert currently requires `_id` to be present in the filter, because the new document needs a primary key.
:::

## Delete

`deleteOne` removes the first match and `deleteMany` removes all matches. Both return a `DeleteResult`:

```php
$result = $collection->deleteOne(['email' => 'max@example.com']);

echo $result->getDeletedCount();

$collection->deleteMany(['age' => ['$lt' => 18]]);
```
## Find and modify

The atomic helpers return the matched document and change it in one step. `findOneAndUpdate` and `findOneAndReplace` apply a change, while `findOneAndDelete` removes the match:

```php
$old = $collection->findOneAndUpdate(
    ['_id' => '1'],
    ['$inc' => ['profile.stats.score' => 1]],
);

$removed = $collection->findOneAndDelete(['_id' => '2']);
```
## Bulk writes

`bulkWrite` runs many write operations against a collection in a single database transaction. If any operation fails, the whole batch is rolled back, so the collection never ends up in a half-written state.

Each entry in the list is a single-key array naming the operation, with its arguments as a positional list. The arguments mirror the matching standalone method:

```php
$result = $collection->bulkWrite([
    [
        'insertOne' => [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
        ],
    ],
    [
        'updateOne' => [
            ['email' => 'jane@example.com'],
            ['$set' => ['age' => 31]],
        ],
    ],
    [
        'deleteOne' => [
            ['email' => 'max@example.com'],
        ],
    ],
]);
```
The supported operations and their arguments are:

| Operation | Arguments |
|---|---|
| `insertOne` | `[$document]` |
| `updateOne` | `[$filter, $update, $options?]` |
| `updateMany` | `[$filter, $update, $options?]` |
| `replaceOne` | `[$filter, $replacement, $options?]` |
| `deleteOne` | `[$filter]` |
| `deleteMany` | `[$filter]` |

`bulkWrite` returns a `BulkWriteResult` that aggregates the counts across every operation in the batch:

```php
echo $result->getInsertedCount();
echo $result->getMatchedCount();
echo $result->getModifiedCount();
echo $result->getDeletedCount();

$result->getInsertedIds(); // ids of inserted documents
```
:::warning
All operations run in one transaction. A failure in any of them rolls back every change in the batch, including ones that already succeeded.
:::

## Learn more

* [How to filter and shape results when querying](querying.md)
* [How to modify documents with update operators](update-operators.md)
* [How to reshape data with aggregation](aggregation.md)
