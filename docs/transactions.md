# Transactions

A session groups several operations into a single transaction: either every write is applied, or none of them are. The API mirrors `mongodb/mongodb`, so code written against `startSession`, `startTransaction`, and `commitTransaction` keeps working unchanged.

Because a [client](connection.md) owns exactly one PostgreSQL connection, a transaction wraps a plain `BEGIN` / `COMMIT` / `ROLLBACK` on that connection.

## Running a transaction

Start a session on the client, open a transaction, and commit it once every operation succeeded:

```php
$session = $client->startSession();
$session->startTransaction();

try {
    $accounts->updateOne(['_id' => 'alice'], ['$inc' => ['balance' => -100]], ['session' => $session]);
    $accounts->updateOne(['_id' => 'bob'], ['$inc' => ['balance' => 100]], ['session' => $session]);

    $session->commitTransaction();
} catch (Throwable $e) {
    $session->abortTransaction();

    throw $e;
} finally {
    $session->endSession();
}
```
`abortTransaction` rolls everything back. `endSession` releases the session and rolls back a transaction that is still open; the underlying connection stays open and can start a new session.

## The callback style

`withTransaction` takes care of commit and rollback for you. It commits when the callback returns and rolls back when it throws, then re-throws the exception:

```php
$session = $client->startSession();

$session->withTransaction(static function ($session) use ($accounts): void {
    $accounts->updateOne(['_id' => 'alice'], ['$inc' => ['balance' => -100]], ['session' => $session]);
    $accounts->updateOne(['_id' => 'bob'], ['$inc' => ['balance' => 100]], ['session' => $session]);
});
```
If PostgreSQL rejects the transaction with a serialization failure or a deadlock, `withTransaction` retries the callback a few times before giving up. Make sure the callback has no side effects outside the database, since it may run more than once.

## The session option

Every collection method accepts a `session` option, just like the MongoDB driver. Passing it keeps your code portable. Rango runs all operations on the one connection the client holds, so once a transaction is open on that connection, every following operation takes part in it whether or not you pass the option.

:::note
Only pass a session that came from the same client. A session from another client points at a different connection and its transaction would not cover the operation.
:::

## Isolation level

By default a transaction runs at PostgreSQL's `READ COMMITTED` level. Pass a `readConcern` to raise it:

```php
$session->startTransaction(['readConcern' => 'snapshot']);
```
`snapshot` maps to `REPEATABLE READ` and `linearizable` maps to `SERIALIZABLE`. A `SERIALIZABLE` transaction is the case that can fail with a serialization error under concurrency, which is why `withTransaction` retries.

## Bulk writes

`bulkWrite` already runs in its own transaction. Inside a session transaction it joins the surrounding one instead of opening a nested transaction, so a failing bulk write aborts the whole transaction.

## Limitations

* A session runs one transaction at a time. Calling `startTransaction` again before committing or aborting throws.
* Read the results of `find` and `aggregate` inside the transaction. A cursor that is still open when you commit may not see a consistent snapshot afterwards.
* Sessions are not causally consistent across connections the way MongoDB sessions are; there is only ever the single client connection.

## Learn more

* [How a client maps to a PostgreSQL connection](connection.md)
* [How CRUD operations translate to SQL](how-it-works.md)
* [Bulk writes](crud-operations.md)
