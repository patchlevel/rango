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

`$match` filters documents using the same syntax as [query operators](querying.md), including [`$expr`](querying.md#evaluation-operators) for [expression](#expressions)-based conditions. `$sort`, `$limit`, and `$skip` order and page the stream just like the [read options](querying.md#sorting):

```php
$collection->aggregate([
    ['$match' => ['age' => ['$gte' => 18]]],
    ['$match' => ['$expr' => ['$gt' => ['$spent', '$budget']]]],
    ['$sort' => ['age' => -1]],
    ['$skip' => 10],
    ['$limit' => 5],
]);
```
## Reshaping stages

`$project` selects fields with `1`/`0`, and `$unwind` expands an array field into one document per element:

```php
$collection->aggregate([
    ['$project' => ['name' => 1, '_id' => 0]],
]);

$collection->aggregate([
    ['$unwind' => '$tags'],
]);
```

`$project` can also rename fields and compute new ones from [expressions](#expressions); once a computed field is present the stage rebuilds the document from the keys you list (plus `_id` unless you set `'_id' => 0`):

```php
$collection->aggregate([
    [
        '$project' => [
            '_id' => 0,
            'fullName' => ['$concat' => ['$first', ' ', '$last']],
            'sku' => '$productId',
            'total' => ['$multiply' => ['$price', '$quantity']],
        ],
    ],
]);
```

`$addFields` (and its alias `$set`) adds or overwrites fields while keeping the rest of the document. Dotted keys write into nested objects:

```php
$collection->aggregate([
    [
        '$addFields' => [
            'total' => ['$multiply' => ['$price', '$quantity']],
            'audit.reviewed' => true,
        ],
    ],
]);
```

`$unset` removes one or more fields, and `$replaceRoot` / `$replaceWith` promote an [expression](#expressions) to be the new document:

```php
$collection->aggregate([
    ['$unset' => ['ssn', 'audit.internalNote']],
    ['$replaceRoot' => ['newRoot' => '$profile']],
    ['$replaceWith' => ['id' => '$_id', 'name' => '$profile.handle']],
]);
```

`$unwind` also accepts the document form with `preserveNullAndEmptyArrays` to keep documents whose array is missing, `null`, or empty, and `includeArrayIndex` to add the position of each element:

```php
$collection->aggregate([
    [
        '$unwind' => [
            'path' => '$tags',
            'preserveNullAndEmptyArrays' => true,
            'includeArrayIndex' => 'tagIndex',
        ],
    ],
]);
```

A field that is neither an array nor `null` is treated as a single-element array.
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
            'items' => ['$push' => '$sku'],
            'customers' => ['$addToSet' => '$customerId'],
        ],
    ],
]);
```
The supported accumulators are `$sum`, `$avg`, `$min`, `$max`, `$first`, `$last`, `$push`, `$addToSet`, and `$count`. Use `['$sum' => 1]` or `['$count' => []]` to count documents in each group.

`_id` may also be a document to group by several keys at once, or any [expression](#expressions). Accumulator arguments are expressions too, so you can group over a computed value:

```php
$collection->aggregate([
    [
        '$group' => [
            '_id' => ['status' => '$status', 'country' => '$address.country'],
            'revenue' => ['$sum' => ['$multiply' => ['$price', '$quantity']]],
        ],
    ],
]);
```

## Expressions

Wherever a stage expects an expression (`$project`, `$addFields`/`$set`, `$group` keys and accumulator arguments) you can use a field reference (`'$field'`, dot notation allowed), a literal, or an operator object. The supported operators are:

* Arithmetic: `$add`, `$subtract`, `$multiply`, `$divide`, `$mod`, `$abs`, `$ceil`, `$floor`, `$round`
* String: `$concat`, `$toUpper`, `$toLower`, `$substr`, `$strLenCP`
* Comparison: `$eq`, `$ne`, `$gt`, `$gte`, `$lt`, `$lte`
* Boolean: `$and`, `$or`, `$not`
* Conditional: `$cond`, `$switch`, `$ifNull`
* Type: `$toString`, `$toInt`, `$toLong`, `$toDouble`, `$toBool`
* Date: `$year`, `$month`, `$dayOfMonth`, `$hour`, `$minute`, `$second`, `$dateToString`
* Array: `$size`, `$isArray`, `$arrayElemAt`, `$first`, `$last`, `$in`, `$concatArrays`, `$reverseArray`, `$slice`
* `$literal` to pass a value through untouched

```php
$collection->aggregate([
    [
        '$project' => [
            'label' => [
                '$cond' => [
                    ['$gte' => ['$score', 60]],
                    'pass',
                    'fail',
                ],
            ],
            'month' => ['$dateToString' => ['format' => '%Y-%m', 'date' => '$createdAt']],
        ],
    ],
]);
```

:::note
Date operators expect ISO 8601 date strings, which is how Rango stores dates in JSONB. `$$ROOT` and `$$NOW` are the only system variables.
:::

## Counting

`$count` collapses the stream into a single document holding the number of documents that reached it:

```php
$collection->aggregate([
    ['$match' => ['status' => 'paid']],
    ['$count' => 'paidOrders'],
]);
```

`$sortByCount` groups by an [expression](#expressions) and returns `{_id, count}` documents ordered by `count` descending. It is shorthand for a `$group` with `['$sum' => 1]` followed by a `$sort`:

```php
$collection->aggregate([
    ['$unwind' => '$tags'],
    ['$sortByCount' => '$tags'],
]);
```

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
Only the stages, accumulators, and [expression](#expressions) operators listed here are implemented. Array iteration (`$map`, `$filter`, `$reduce`), `$facet`, and window functions are out of scope, as noted under [limitations](how-it-works.md#limitations).
:::

## Learn more

* [How to filter with the same operators used by `$match`](querying.md)
* [How to read and iterate the resulting cursor](crud-operations.md)
* [How Rango compiles a pipeline into SQL](how-it-works.md)
