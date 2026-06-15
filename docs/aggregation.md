# Aggregation

Aggregation runs documents through a pipeline of stages, where each stage transforms the stream produced by the one before it. It is the right tool when a single [query](querying.md) is not enough, for example to group, reshape, or join documents.

You pass a pipeline as a list of stages to `aggregate`, which returns a [Cursor](crud-operations.md):

```php
$cursor = $collection->aggregate([
    ['$match' => ['status' => 'paid']],
    ['$group' => ['_id' => '$userId', 'total' => ['$sum' => '$amount']]],
    ['$sort' => ['total' => -1]],
]);

foreach ($cursor as $row) {
    echo $row['_id'] . ': ' . $row['total'] . "\n";
}
```
## Filtering and ordering stages

`$match` filters documents using the same syntax as [query operators](querying.md). `$sort`, `$limit`, and `$skip` order and page the stream just like the [read options](querying.md#sorting):

```php
$collection->aggregate([
    ['$match' => ['age' => ['$gte' => 18]]],
    ['$sort' => ['age' => -1]],
    ['$skip' => 10],
    ['$limit' => 5],
]);
```
## Reshaping stages

`$project` selects and renames fields, and `$unwind` expands an array field into one document per element:

```php
$collection->aggregate([
    ['$project' => ['name' => 1, '_id' => 0]],
]);

$collection->aggregate([
    ['$unwind' => '$tags'],
]);
```
## Grouping

`$group` buckets documents by an `_id` expression and computes accumulators per bucket. A field reference is written with a leading `$`:

```php
$collection->aggregate([
    [
        '$group' => [
            '_id' => '$status',
            'orders' => ['$sum' => 1],
            'revenue' => ['$sum' => '$total'],
            'average' => ['$avg' => '$total'],
            'highest' => ['$max' => '$total'],
            'lowest' => ['$min' => '$total'],
        ],
    ],
]);
```
The supported accumulators are `$sum`, `$avg`, `$min`, `$max`, `$first`, and `$last`. Use `['$sum' => 1]` to count documents in each group.

## Joining collections

`$lookup` performs a left outer join against another collection in the same database. It matches `localField` against `foreignField` and stores the matches in the array named by `as`:

```php
$client->selectCollection('app', 'users')->aggregate([
    [
        '$lookup' => [
            'from' => 'orders',
            'localField' => '_id',
            'foreignField' => 'userId',
            'as' => 'orders',
        ],
    ],
]);
```
Each `users` document gains an `orders` array holding the matching `orders` documents, or an empty array when there are none.

:::note
Only the stages and accumulators listed here are implemented. Complex aggregation expressions are out of scope, as noted under [limitations](how-it-works.md#limitations).
:::

## Learn more

* [How to filter with the same operators used by `$match`](querying.md)
* [How to read and iterate the resulting cursor](crud-operations.md)
* [How Rango compiles a pipeline into SQL](how-it-works.md)
