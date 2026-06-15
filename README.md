[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Frango%2F1.1.x)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/rango/1.1.x)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/rango/v)](https://packagist.org/packages/patchlevel/rango)
[![License](https://poser.pugx.org/patchlevel/rango/license)](https://packagist.org/packages/patchlevel/rango)

# Rango

<p align="center">
  <img src="logo.png" width="50%">
</p>

Rango is a high-performance PHP library that reimplements the **MongoDB PHP API** on top of **PostgreSQL** using the
power of `JSONB`.

It provides a drop-in compatible API, allowing you to use familiar MongoDB-style operations while storing your data in a
reliable PostgreSQL database. This is ideal for applications that want to leverage PostgreSQL's ACID compliance and
ecosystem without giving up the flexible document-based development experience of MongoDB.

## Features

* [Drop-in MongoDB API](https://patchlevel.dev/docs/rango/latest/getting-started) with `Client`, `Database`, and `Collection`
* [CRUD operations](https://patchlevel.dev/docs/rango/latest/crud-operations) like `insertOne`, `find`, `updateMany`, and `deleteOne`
* [Rich query operators](https://patchlevel.dev/docs/rango/latest/querying) such as `$gt`, `$in`, `$or`, and `$elemMatch`
* [Update operators](https://patchlevel.dev/docs/rango/latest/update-operators) like `$set`, `$inc`, `$push`, and `$rename`
* [Projection and sorting](https://patchlevel.dev/docs/rango/latest/querying#projection) with dot-notation support
* [Aggregation pipelines](https://patchlevel.dev/docs/rango/latest/aggregation) with `$match`, `$group`, `$unwind`, and `$lookup`
* [Bulk writes](https://patchlevel.dev/docs/rango/latest/crud-operations#bulk-writes) wrapped in a single transaction
* [Index management](https://patchlevel.dev/docs/rango/latest/indexes) backed by native PostgreSQL indexes

## Installation

```bash
composer require patchlevel/rango
```

## Documentation

* Latest [Docs](https://patchlevel.dev/docs/rango/latest)
* Related [Blog](https://patchlevel.dev/blog)

## Integration

* [odm](https://github.com/patchlevel/odm)
* [event-sourcing](https://github.com/patchlevel/event-sourcing)
* [hydrator](https://github.com/patchlevel/hydrator)

## Contributing

We are open to contributions as long as they are in line with
our [BC-Policy](https://patchlevel.dev/our-backward-compatibility-promise).

Also note that the `composer.lock` is always generated with the newest supported PHP version as this is the version our tools run in the CI.
