# How it Works

Rango speaks the MongoDB PHP API but stores everything in PostgreSQL. Understanding the mapping helps you reason about performance, write SQL against the same tables, and know what to expect from each operation.

## The mapping

Rango translates MongoDB concepts into native PostgreSQL structures:

| MongoDB concept | PostgreSQL structure |
|---|---|
| Database | Schema |
| Collection | Table with `_id` and `data` columns |
| Document | Row, stored as `JSONB` in `data` |
| Index | B-tree index on a `JSONB` expression |
| Query operators | `JSONB` operators and conditions |

Every collection is a table with two columns: a `TEXT` `_id` primary key and a `JSONB` `data` column holding the full document. The `_id` is also kept inside the document so reads return it like any other field.

## Lazy schema creation

You never run migrations by hand. The first write to a [collection](crud-operations.md) creates the schema and table if they do not exist:

```sql
CREATE SCHEMA IF NOT EXISTS "app";
CREATE TABLE IF NOT EXISTS "app"."users" (_id TEXT PRIMARY KEY, data JSONB NOT NULL);
```
:::success
Selecting a database or collection never touches PostgreSQL. The structure is created on demand the first time you insert or update.
:::

## Generated ids

When you insert a document without an `_id`, Rango generates a random 24-character hex string and uses it as the primary key. Provide your own `_id` whenever you need a stable, meaningful key.

## From queries to SQL

[Query operators](querying.md) compile to PostgreSQL `JSONB` conditions, dot-notation paths become `->` and `->>` accessors, [update operators](update-operators.md) become `jsonb_set`-style expressions, and [aggregation](aggregation.md) pipelines become nested `SELECT` statements. Because the result is ordinary SQL against ordinary tables, you can inspect, back up, and query the data with any PostgreSQL tool.

## Limitations

Rango covers the most common MongoDB use cases, but it does not reimplement the entire MongoDB feature set. The following features are currently out of scope:

* **Geospatial queries** such as `$near` and `$geoWithin`
* **Capped collections**
* **Text search** with MongoDB-specific syntax and text indexes
* **Advanced aggregation expressions**: arithmetic, string, comparison, boolean, conditional, date, and array operators are supported (see [aggregation](aggregation.md)), but array iteration (`$map`, `$reduce`, `$filter`), `$facet`, and window functions are not
* **Special index types**: only ascending and descending [indexes](indexes.md) are supported, so geospatial (`2dsphere`), text, sparse, and TTL indexes are not, and the matching `IndexInfo` checks always report `false`

[Upserts](update-operators.md) also need `_id` to be present in the filter, because Rango builds the primary key of the inserted document from it. An upsert without `_id` in the filter raises an exception.

:::note
This list reflects the current state of Rango. Features may be added over time, so check the changelog and the [aggregation](aggregation.md) and [indexes](indexes.md) pages for what is available in your version.
:::

## Learn more

* [How to connect a client to PostgreSQL](connection.md)
* [How to create indexes on the JSONB column](indexes.md)
* [How to run aggregation pipelines](aggregation.md)
