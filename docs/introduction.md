# Rango

Rango is a high-performance PHP library that reimplements the MongoDB PHP API on top of PostgreSQL using the power of `JSONB`.

It provides a drop-in compatible API, so you can use familiar MongoDB-style operations while storing your data in a reliable PostgreSQL database. This is ideal for applications that want PostgreSQL's ACID compliance and ecosystem without giving up the flexible document-based development experience of MongoDB.

## Features

* [Drop-in MongoDB API](getting-started.md) with `Client`, `Database`, and `Collection`
* [CRUD operations](crud-operations.md) like `insertOne`, `find`, `updateMany`, and `deleteOne`
* [Rich query operators](querying.md) such as `$gt`, `$in`, `$or`, and `$elemMatch`
* [Update operators](update-operators.md) like `$set`, `$inc`, `$push`, and `$rename`
* [Projection and sorting](querying.md#projection) with dot-notation support
* [Aggregation pipelines](aggregation.md) with `$match`, `$group`, `$unwind`, and `$lookup`
* [Bulk writes](crud-operations.md#bulk-writes) wrapped in a single transaction
* [Index management](indexes.md) backed by native PostgreSQL indexes

## Installation

```bash
composer require patchlevel/rango
```
Rango needs the PDO extension and a PostgreSQL connection. The MongoDB extension is only required for the test suite, not at runtime.

## Integration

* [odm](https://github.com/patchlevel/odm)
* [event-sourcing](https://github.com/patchlevel/event-sourcing)
* [hydrator](https://github.com/patchlevel/hydrator)

:::tip
New to Rango? Start with the [getting started](getting-started.md) tutorial, which builds a small application step by step.
:::
