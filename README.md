# Rango

<p align="center">
  <img src="logo.png" width="50%">
</p>

Rango is a high-performance PHP library that reimplements the **MongoDB PHP API** on top of **PostgreSQL** using the power of `JSONB`.

It provides a drop-in compatible API, allowing you to use familiar MongoDB-style operations while storing your data in a reliable PostgreSQL database. This is ideal for applications that want to leverage PostgreSQL's ACID compliance and ecosystem without giving up the flexible document-based development experience of MongoDB.

## 🚀 Why Rango?

- **PostgreSQL Reliability**: Benefit from PostgreSQL's mature engine, backups, and ACID transactions.
- **JSONB Performance**: Rango leverages PostgreSQL's binary JSON format (`JSONB`) for efficient storage and indexing.
- **Seamless Migration**: Switch between PostgreSQL and MongoDB with minimal code changes.
- **Hybrid Power**: Mix relational and document data within the same database.

## 📦 Installation

```bash
composer require patchlevel/rango
```

## 🛠 How it Works

Rango translates MongoDB queries into optimized PostgreSQL SQL statements. 

- **Databases** are mapped to **PostgreSQL Schemas**.
- **Collections** are mapped to **PostgreSQL Tables** with a single `data` column of type `JSONB`.
- **Indexes** are created as **GIN or B-tree indexes** on the JSONB field.
- **Queries** are translated using PostgreSQL's rich set of JSONB operators (like `@>`, `?`, `->>`).

## 🚦 Quick Start

```php
use Patchlevel\Rango\Client;

// Connect to PostgreSQL using a standard PDO DSN
$client = new Client('pgsql:host=localhost;port=5432;dbname=app;user=postgres;password=postgres');

// Select database and collection (auto-created on first write)
$collection = $client->selectDatabase('test')->selectCollection('users');

// Insert a document (automatically generates an _id if missing)
$collection->insertOne([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'tags' => ['php', 'postgres'],
    'metadata' => ['logins' => 0]
]);

// Find documents using MongoDB syntax
$users = $collection->find([
    'tags' => 'php',
    'metadata.logins' => ['$lt' => 10]
]);

foreach ($users as $user) {
    echo "Found: " . $user['name'] . " (" . $user['_id'] . ")\n";
}

// Atomic updates
$collection->updateOne(
    ['email' => 'john@example.com'],
    ['$inc' => ['metadata.logins' => 1]]
);
```

## ✨ Supported Features

### CRUD Operations
| Category | Supported Methods |
| --- | --- |
| **Create** | `insertOne`, `insertMany` |
| **Read** | `find`, `findOne`, `countDocuments`, `distinct` |
| **Update** | `updateOne`, `updateMany`, `replaceOne`, `bulkWrite` |
| **Delete** | `deleteOne`, `deleteMany` |
| **Atomic** | `findOneAndUpdate`, `findOneAndReplace`, `findOneAndDelete` |

### Query Operators
| Category | Operators |
| --- | --- |
| **Comparison** | `$eq`, `$ne`, `$gt`, `$gte`, `$lt`, `$lte`, `$in`, `$nin` |
| **Logical** | `$and`, `$or`, `$nor`, `$not` |
| **Element** | `$exists`, `$type` |
| **Evaluation** | `$regex`, `$mod` |
| **Array** | `$all`, `$size`, `$elemMatch` |

Projection supports dot-notation in both inclusion and exclusion (e.g. `['profile.stats.score' => 0]`).

### Aggregation Framework
| Feature | Details |
| --- | --- |
| **Stages** | `$match`, `$sort`, `$limit`, `$skip`, `$project`, `$unwind`, `$group`, `$lookup` (Join) |
| **Accumulators** | `$sum`, `$avg`, `$min`, `$max`, `$first`, `$last` |

### Update Operators
| Category | Operators |
| --- | --- |
| **Field** | `$set`, `$setOnInsert`, `$inc`, `$mul`, `$unset`, `$rename`, `$min`, `$max`, `$currentDate` (incl. dot-notation) |
| **Array** | `$push` (inc. `$each`), `$pull`, `$addToSet`, `$pop` |
| **Bitwise** | `$bit` (`and`, `or`, `xor`) |

### Management
- **Index Management**: `createIndex`, `dropIndex`, `listIndexes`.
- **Schema Management**: `listDatabases`, `listCollections`, `renameCollection`, `drop`.

## ⚠️ Current Limitations

While Rango covers the most common use cases, some MongoDB features are not yet implemented:
- **Geospatial queries** (`$near`, `$geoWithin`, etc.)
- **Capped collections**
- **Text search** (MongoDB-specific syntax)
- **Complex Aggregation expressions** (only basic accumulators are supported)

## 👩‍💻 Development

### Prerequisites
- PHP 8.3+
- Docker & Docker Compose (for integration tests)

### Running Tests

We test Rango against **both** a real MongoDB and PostgreSQL to ensure 100% compatibility.

```bash
docker compose up -d
vendor/bin/phpunit
```

---
Built with ❤️ by the patchlevel team.
