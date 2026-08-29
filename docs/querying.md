# Querying

Reading documents has two parts: a filter that selects which documents match, and options that shape and order the results. This page covers both. Filters are also accepted by the update and delete methods and by the `$match` stage of an [aggregation](aggregation.md).

A filter is an array where each key is a field and each value is either a literal to match or an operator expression. Rango translates these into PostgreSQL `JSONB` conditions.

## Matching fields

A plain value matches documents where the field equals that value. Nested fields use dot-notation, and matching an array field against a scalar checks whether the array contains that value:

```php
$collection->find(['name' => 'John Doe']);
$collection->find(['profile.stats.score' => 42]);
$collection->find(['tags' => 'php']); // documents whose tags array contains "php"
```
## Comparison operators

Comparison operators wrap the value in an array keyed by the operator:

```php
$collection->find(['age' => ['$gt' => 20]]);
$collection->find(['age' => ['$gte' => 20]]);
$collection->find(['age' => ['$lt' => 30]]);
$collection->find(['age' => ['$lte' => 30]]);
$collection->find(['age' => ['$ne' => 30]]);
$collection->find(['age' => ['$in' => [20, 40]]]);
$collection->find(['age' => ['$nin' => [20, 40]]]);
```
| Operator | Matches when the field |
|---|---|
| `$eq` | equals the value |
| `$ne` | does not equal the value |
| `$gt` / `$gte` | is greater than / greater than or equal |
| `$lt` / `$lte` | is less than / less than or equal |
| `$in` | is one of the listed values |
| `$nin` | is none of the listed values |

## Logical operators

Logical operators combine sub-filters. `$and`, `$or`, and `$nor` take a list of filters, while `$not` negates a single operator expression:

```php
$collection->find([
    '$or' => [
        ['age' => ['$lt' => 18]],
        ['age' => ['$gte' => 65]],
    ],
]);

$collection->find([
    '$and' => [
        ['tags' => 'php'],
        ['age' => ['$gte' => 21]],
    ],
]);

$collection->find(['age' => ['$not' => ['$gt' => 30]]]);
```
## Element operators

Element operators test the presence or type of a field:

```php
$collection->find(['name' => ['$exists' => true]]);
$collection->find(['deletedAt' => ['$exists' => false]]);
$collection->find(['age' => ['$type' => 'number']]);
```
## Evaluation operators

`$regex` matches a string field against a pattern, and `$mod` matches numbers by their remainder:

```php
$collection->find(['email' => ['$regex' => '@example\\.com$']]);
$collection->find(['age' => ['$mod' => [2, 0]]]); // even ages
```

`$expr` matches documents against an [aggregation expression](aggregation.md#expressions), which is the way to compare two fields of the same document:

```php
$collection->find(['$expr' => ['$gt' => ['$spent', '$budget']]]);
```
## Array operators

Array operators inspect array fields. `$all` requires every listed value, `$size` matches by length, and `$elemMatch` matches array elements against a sub-filter:

```php
$collection->find(['tags' => ['$all' => ['php', 'postgres']]]);
$collection->find(['tags' => ['$size' => 2]]);

$collection->find([
    'orders' => [
        '$elemMatch' => ['total' => ['$gt' => 100], 'status' => 'paid'],
    ],
]);
```
:::tip
Operators combine freely. A single field can carry several operators, and several fields act as an implicit `$and`, so `['age' => ['$gte' => 18, '$lt' => 65], 'tags' => 'php']` reads naturally.
:::

## Projection

Read operations accept an options array as their last argument. A projection selects which fields come back. Use `1` to include a field and `0` to exclude it, with dot-notation for nested fields:

```php
// only return name (and _id, which is included by default)
$user = $collection->findOne(['_id' => '1'], ['projection' => ['name' => 1]]);

// return everything except age
$user = $collection->findOne(['_id' => '1'], ['projection' => ['age' => 0]]);

// include name but drop the _id
$user = $collection->findOne(['_id' => '1'], ['projection' => ['name' => 1, '_id' => 0]]);

// exclude a deeply nested field
$user = $collection->findOne(['_id' => '1'], ['projection' => ['profile.stats.score' => 0]]);
```
:::note
As in MongoDB, `_id` is included unless you explicitly exclude it with `'_id' => 0`.
:::

## Sorting

The `sort` option orders results by one or more fields. Use `1` for ascending and `-1` for descending, with dot-notation for nested fields:

```php
$cursor = $collection->find([], ['sort' => ['name' => 1]]);

$cursor = $collection->find([], ['sort' => ['age' => -1, 'name' => 1]]);

$cursor = $collection->find([], ['sort' => ['profile.stats.score' => -1]]);
```
## Limit and skip

`limit` caps the number of returned documents and `skip` offsets the start. Together with `sort` they implement pagination:

```php
// second page of 20 results, newest first
$cursor = $collection->find([], [
    'sort' => ['createdAt' => -1],
    'limit' => 20,
    'skip' => 20,
]);
```
:::tip
Rango maps `sort`, `limit`, and `skip` to SQL `ORDER BY`, `LIMIT`, and `OFFSET`, so paging stays efficient.
:::

## Learn more

* [How to change the matched documents with update operators](update-operators.md)
* [How to run multi-stage queries with aggregation](aggregation.md)
* [How to speed up filtered and sorted reads with indexes](indexes.md)
