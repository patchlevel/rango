# Update Operators

Update operators describe how to change matching documents without replacing them. You pass them to [updateOne, updateMany, findOneAndUpdate](crud-operations.md), and to update operations inside a [bulk write](crud-operations.md#bulk-writes). Each operator is keyed by its name and takes a map of fields to values.

All operators support dot-notation, so you can reach into nested documents.

## Field operators

Field operators set, remove, and adjust individual values:

```php
$collection->updateOne(['_id' => '1'], [
    '$set' => ['name' => 'John Doe', 'profile.stats.score' => 100],
    '$unset' => ['temporary' => ''],
    '$rename' => ['username' => 'name'],
]);
```
| Operator | Effect |
|---|---|
| `$set` | sets the field to the value |
| `$setOnInsert` | sets the field only when an upsert inserts a new document |
| `$unset` | removes the field |
| `$rename` | renames the field |
| `$inc` | increments the field by the value |
| `$mul` | multiplies the field by the value |
| `$min` | sets the field only if the value is smaller |
| `$max` | sets the field only if the value is larger |
| `$currentDate` | sets the field to the current date |

```php
$collection->updateOne(['_id' => '1'], [
    '$inc' => ['profile.stats.score' => 5],
    '$mul' => ['profile.stats.multiplier' => 2],
    '$min' => ['lowest' => 10],
    '$max' => ['highest' => 90],
    '$currentDate' => ['updatedAt' => true],
]);
```
## Array operators

Array operators modify list fields in place. `$push` appends, `$pull` removes by match, `$addToSet` appends only if absent, and `$pop` removes from an end:

```php
$collection->updateOne(['_id' => '1'], [
    '$push' => ['tags' => 'mongodb'],
    '$addToSet' => ['roles' => 'admin'],
]);

$collection->updateOne(['_id' => '1'], [
    '$pull' => ['tags' => 'deprecated'],
    '$pop' => ['history' => 1], // 1 removes the last, -1 the first
]);
```
Use `$each` with `$push` to append several values at once:

```php
$collection->updateOne(['_id' => '1'], [
    '$push' => ['tags' => ['$each' => ['php', 'postgres']]],
]);
```
## Bitwise operator

`$bit` applies a bitwise `and`, `or`, or `xor` to an integer field:

```php
$collection->updateOne(['_id' => '1'], [
    '$bit' => ['flags' => ['or' => 4]],
]);
```
## Upsert and setOnInsert

When you combine the `upsert` option with `$setOnInsert`, the extra fields are only written if a new document is created:

```php
$collection->updateOne(
    ['_id' => 'user-42'],
    [
        '$set' => ['name' => 'New User'],
        '$setOnInsert' => ['createdAt' => '2026-01-01'],
    ],
    ['upsert' => true],
);
```
:::warning
Upsert needs `_id` in the filter so Rango can build the primary key for the inserted document.
:::

## Learn more

* [How to select the documents to update with query operators](querying.md)
* [How to apply many updates in one transaction with bulk write](crud-operations.md#bulk-writes)
* [How to replace or atomically modify documents](crud-operations.md)
