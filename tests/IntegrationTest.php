<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Tests;

use MongoDB\Client as MongoDbClient;
use MongoDB\Collection as MongoDbCollection;
use MongoDB\Database as MongoDbDatabase;
use MongoDB\Exception\InvalidArgumentException;
use MongoDB\Model\IndexInfo as MongoIndexInfo;
use Patchlevel\Rango\Client as RangoClient;
use Patchlevel\Rango\Collection as RangoCollection;
use Patchlevel\Rango\Database as RangoDatabase;
use Patchlevel\Rango\Model\IndexInfo;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_map;
use function array_values;
use function is_array;
use function iterator_to_array;
use function json_encode;
use function sort;

/** @internal */
abstract class IntegrationTest extends TestCase
{
    protected MongoDbCollection|RangoCollection $collection;

    abstract protected function getClient(): MongoDbClient|RangoClient;

    abstract protected function getCollection(): MongoDbCollection|RangoCollection;

    abstract protected function getDatabase(): MongoDbDatabase|RangoDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = $this->getCollection();
        $this->collection->drop();
    }

    public function testInsertOneAndFind(): void
    {
        $result = $this->collection->insertOne(['_id' => '1', 'name' => 'foo']);

        self::assertEquals(1, $result->getInsertedCount());
        self::assertEquals('1', $result->getInsertedId());
        self::assertTrue($result->isAcknowledged());

        $result = $this->collection->find(['name' => 'foo']);
        $docs = iterator_to_array($result);

        self::assertCount(1, $docs);
        self::assertEquals('1', $docs[0]['_id']);
        self::assertEquals('foo', $docs[0]['name']);
    }

    public function testInsertManyAndCount(): void
    {
        $result = $this->collection->insertMany([
            ['_id' => '1', 'name' => 'foo'],
            ['_id' => '2', 'name' => 'bar'],
        ]);

        self::assertEquals(2, $result->getInsertedCount());
        self::assertEquals(['1', '2'], $result->getInsertedIds());
        self::assertTrue($result->isAcknowledged());

        self::assertEquals(2, $this->collection->countDocuments());
        self::assertEquals(1, $this->collection->countDocuments(['name' => 'foo']));
    }

    public function testFindOne(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo']);

        $doc = $this->collection->findOne(['_id' => '1']);

        self::assertNotNull($doc);
        self::assertEquals('1', $doc['_id']);
        self::assertEquals('foo', $doc['name']);
    }

    public function testFindOneNotFound(): void
    {
        $doc = $this->collection->findOne(['_id' => 'missing']);

        self::assertNull($doc);
    }

    public function testUpdateOne(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo']);
        $result = $this->collection->updateOne(['_id' => '1'], ['$set' => ['name' => 'bar']]);

        self::assertEquals(1, $result->getMatchedCount());
        self::assertEquals(1, $result->getModifiedCount());
        self::assertTrue($result->isAcknowledged());

        $doc = $this->collection->findOne(['_id' => '1']);

        self::assertNotNull($doc);
        self::assertEquals('bar', $doc['name']);
    }

    public function testDeleteOne(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo']);
        self::assertEquals(1, $this->collection->countDocuments());

        $result = $this->collection->deleteOne(['_id' => '1']);

        self::assertEquals(1, $result->getDeletedCount());
        self::assertTrue($result->isAcknowledged());

        self::assertEquals(0, $this->collection->countDocuments());
    }

    public function testSort(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'name' => 'b'],
            ['_id' => '2', 'name' => 'a'],
            ['_id' => '3', 'name' => 'c'],
        ]);

        $result = $this->collection->find([], ['sort' => ['name' => 1]]);
        $docs = array_values(iterator_to_array($result));
        self::assertEquals('2', $docs[0]['_id']);
        self::assertEquals('1', $docs[1]['_id']);
        self::assertEquals('3', $docs[2]['_id']);

        $result = $this->collection->find([], ['sort' => ['name' => -1]]);
        $docs = array_values(iterator_to_array($result));
        self::assertEquals('3', $docs[0]['_id']);
        self::assertEquals('1', $docs[1]['_id']);
        self::assertEquals('2', $docs[2]['_id']);
    }

    public function testProjection(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo', 'age' => 42]);

        $doc = $this->collection->findOne(['_id' => '1'], ['projection' => ['name' => 1]]);
        self::assertArrayHasKey('name', $doc);
        self::assertArrayHasKey('_id', $doc);
        self::assertArrayNotHasKey('age', $doc);

        $doc = $this->collection->findOne(['_id' => '1'], ['projection' => ['age' => 0]]);
        self::assertArrayHasKey('name', $doc);
        self::assertArrayHasKey('_id', $doc);
        self::assertArrayNotHasKey('age', $doc);
    }

    public function testProjectionExcludeId(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo', 'age' => 42]);

        $doc = $this->collection->findOne(['_id' => '1'], ['projection' => ['name' => 1, '_id' => 0]]);
        self::assertArrayHasKey('name', $doc);
        self::assertArrayNotHasKey('_id', $doc);
        self::assertArrayNotHasKey('age', $doc);
    }

    public function testProjectionExcludeNested(): void
    {
        $this->collection->insertOne([
            '_id' => '1',
            'profile' => ['name' => 'foo', 'stats' => ['score' => 5, 'level' => 2]],
        ]);

        $doc = $this->collection->findOne(['_id' => '1'], ['projection' => ['profile.stats.score' => 0]]);

        self::assertArrayHasKey('profile', $doc);
        self::assertArrayHasKey('stats', $doc['profile']);
        self::assertArrayNotHasKey('score', $doc['profile']['stats']);
        self::assertEquals(2, $doc['profile']['stats']['level']);
    }

    public function testReplaceOne(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo', 'age' => 42]);
        $result = $this->collection->replaceOne(['_id' => '1'], ['_id' => '1', 'name' => 'bar']);

        self::assertEquals(1, $result->getMatchedCount());
        self::assertEquals(1, $result->getModifiedCount());

        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals('bar', $doc['name']);
        self::assertArrayNotHasKey('age', $doc);
    }

    public function testIncAndUnset(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo', 'age' => 42]);

        $this->collection->updateOne(['_id' => '1'], ['$inc' => ['age' => 1]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals(43, $doc['age']);

        $this->collection->updateOne(['_id' => '1'], ['$unset' => ['name' => '']]);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertArrayNotHasKey('name', $doc);
        self::assertEquals(43, $doc['age']);
    }

    public function testComparisonOperators(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'age' => 20],
            ['_id' => '2', 'age' => 30],
            ['_id' => '3', 'age' => 40],
        ]);

        self::assertCount(2, iterator_to_array($this->collection->find(['age' => ['$gt' => 20]])));
        self::assertCount(3, iterator_to_array($this->collection->find(['age' => ['$gte' => 20]])));
        self::assertCount(1, iterator_to_array($this->collection->find(['age' => ['$lt' => 30]])));
        self::assertCount(2, iterator_to_array($this->collection->find(['age' => ['$lte' => 30]])));
        self::assertCount(2, iterator_to_array($this->collection->find(['age' => ['$ne' => 30]])));
        self::assertCount(2, iterator_to_array($this->collection->find(['age' => ['$in' => [20, 40]]])));
        self::assertCount(1, iterator_to_array($this->collection->find(['age' => ['$nin' => [20, 40]]])));
    }

    public function testInAndNinEmpty(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'age' => 20],
            ['_id' => '2', 'age' => 30],
        ]);

        self::assertCount(0, iterator_to_array($this->collection->find(['age' => ['$in' => []]])));
        self::assertCount(2, iterator_to_array($this->collection->find(['age' => ['$nin' => []]])));
    }

    public function testLogicalOperators(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'name' => 'foo', 'age' => 20],
            ['_id' => '2', 'name' => 'bar', 'age' => 30],
            ['_id' => '3', 'name' => 'foo', 'age' => 40],
        ]);

        self::assertCount(1, iterator_to_array($this->collection->find([
            '$and' => [
                ['name' => 'foo'],
                ['age' => ['$gt' => 20]],
            ],
        ])));

        self::assertCount(2, iterator_to_array($this->collection->find([
            '$or' => [
                ['name' => 'bar'],
                ['age' => 40],
            ],
        ])));

        self::assertCount(0, iterator_to_array($this->collection->find([
            '$nor' => [
                ['name' => 'foo'],
                ['age' => 30],
            ],
        ])));

        self::assertCount(2, iterator_to_array($this->collection->find([
            'name' => ['$not' => ['$eq' => 'bar']],
        ])));
    }

    public function testExists(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'name' => 'foo'],
            ['_id' => '2', 'age' => 30],
        ]);

        self::assertCount(1, iterator_to_array($this->collection->find(['name' => ['$exists' => true]])));
        self::assertCount(1, iterator_to_array($this->collection->find(['name' => ['$exists' => false]])));
    }

    public function testExistsWithNull(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'name' => null],
            ['_id' => '2', 'name' => 'foo'],
            ['_id' => '3', 'age' => 30],
        ]);

        self::assertCount(2, iterator_to_array($this->collection->find(['name' => ['$exists' => true]])));
        self::assertCount(1, iterator_to_array($this->collection->find(['name' => ['$exists' => false]])));
    }

    public function testUpsert(): void
    {
        $result = $this->collection->updateOne(['_id' => '1'], ['$set' => ['name' => 'foo']], ['upsert' => true]);
        self::assertEquals(0, $result->getMatchedCount());
        self::assertEquals(1, $result->getUpsertedCount());
        self::assertEquals('1', $result->getUpsertedId());

        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertNotNull($doc);
        self::assertEquals('foo', $doc['name']);

        $result = $this->collection->replaceOne(['_id' => '2'], ['_id' => '2', 'name' => 'bar'], ['upsert' => true]);
        self::assertEquals(0, $result->getMatchedCount());
        self::assertEquals(1, $result->getUpsertedCount());
        self::assertEquals('2', $result->getUpsertedId());

        $doc = $this->collection->findOne(['_id' => '2']);
        self::assertNotNull($doc);
        self::assertEquals('bar', $doc['name']);
    }

    public function testAggregate(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'name' => 'foo', 'age' => 20],
            ['_id' => '2', 'name' => 'bar', 'age' => 30],
            ['_id' => '3', 'name' => 'foo', 'age' => 40],
        ]);

        $pipeline = [
            ['$match' => ['name' => 'foo']],
            ['$sort' => ['age' => 1]],
            ['$project' => ['name' => 1, '_id' => 0]],
        ];

        $result = $this->collection->aggregate($pipeline);
        $docs = array_map(static fn ($doc) => (array)$doc, iterator_to_array($result));

        self::assertCount(2, $docs);
        self::assertEquals(['name' => 'foo'], $docs[0]);
        self::assertEquals(['name' => 'foo'], $docs[1]);
    }

    public function testUnwind(): void
    {
        $this->collection->insertOne([
            '_id' => '1',
            'items' => ['a', 'b', 'c'],
        ]);

        $pipeline = [
            ['$unwind' => '$items'],
        ];

        $result = $this->collection->aggregate($pipeline);
        $docs = array_values(iterator_to_array($result));

        self::assertCount(3, $docs);
        self::assertEquals('a', $docs[0]['items']);
        self::assertEquals('b', $docs[1]['items']);
        self::assertEquals('c', $docs[2]['items']);
    }

    public function testGroup(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'category' => 'A', 'amount' => 10],
            ['_id' => '2', 'category' => 'B', 'amount' => 20],
            ['_id' => '3', 'category' => 'A', 'amount' => 30],
        ]);

        $pipeline = [
            [
                '$group' => [
                    '_id' => '$category',
                    'total' => ['$sum' => '$amount'],
                    'count' => ['$sum' => 1],
                ],
            ],
            ['$sort' => ['_id' => 1]],
        ];

        $result = $this->collection->aggregate($pipeline);
        $docs = array_values(iterator_to_array($result));

        self::assertCount(2, $docs);
        self::assertEquals('A', $docs[0]['_id']);
        self::assertEquals(40, $docs[0]['total']);
        self::assertEquals(2, $docs[0]['count']);

        self::assertEquals('B', $docs[1]['_id']);
        self::assertEquals(20, $docs[1]['total']);
        self::assertEquals(1, $docs[1]['count']);
    }

    public function testFindOneAndModify(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo', 'counter' => 10]);

        // findOneAndUpdate
        $old = $this->collection->findOneAndUpdate(['_id' => '1'], ['$inc' => ['counter' => 1]]);
        self::assertEquals(10, $old['counter']);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals(11, $doc['counter']);

        // findOneAndReplace
        $old = $this->collection->findOneAndReplace(['_id' => '1'], ['_id' => '1', 'name' => 'bar']);
        self::assertEquals('foo', $old['name']);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals('bar', $doc['name']);
        self::assertArrayNotHasKey('counter', $doc);

        // findOneAndDelete
        $old = $this->collection->findOneAndDelete(['_id' => '1']);
        self::assertEquals('bar', $old['name']);
        self::assertEquals(0, $this->collection->countDocuments());
    }

    public function testRenameMinMax(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo', 'val' => 50]);

        $this->collection->updateOne(['_id' => '1'], ['$rename' => ['name' => 'nickname']]);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertArrayNotHasKey('name', $doc);
        self::assertEquals('foo', $doc['nickname']);

        $this->collection->updateOne(['_id' => '1'], ['$min' => ['val' => 25]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals(25, $doc['val']);

        $this->collection->updateOne(['_id' => '1'], ['$min' => ['val' => 75]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals(25, $doc['val']); // Should stay 25

        $this->collection->updateOne(['_id' => '1'], ['$max' => ['val' => 100]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals(100, $doc['val']);

        $this->collection->updateOne(['_id' => '1'], ['$max' => ['val' => 50]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals(100, $doc['val']); // Should stay 100
    }

    public function testDistinct(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'category' => 'A'],
            ['_id' => '2', 'category' => 'B'],
            ['_id' => '3', 'category' => 'A'],
        ]);

        $values = $this->collection->distinct('category');
        sort($values);

        self::assertEquals(['A', 'B'], $values);
    }

    public function testDistinctWithFilter(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'category' => 'A', 'status' => 'open'],
            ['_id' => '2', 'category' => 'B', 'status' => 'closed'],
            ['_id' => '3', 'category' => 'A', 'status' => 'closed'],
        ]);

        $values = $this->collection->distinct('category', ['status' => 'closed']);
        sort($values);

        self::assertEquals(['A', 'B'], $values);
    }

    public function testPushAndPull(): void
    {
        $this->collection->insertOne(['_id' => '1', 'tags' => ['foo']]);

        $this->collection->updateOne(['_id' => '1'], ['$push' => ['tags' => 'bar']]);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals(['foo', 'bar'], (array)$doc['tags']);

        $this->collection->updateOne(['_id' => '1'], ['$pull' => ['tags' => 'foo']]);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals(['bar'], (array)$doc['tags']);
    }

    public function testRegex(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'name' => 'Apple'],
            ['_id' => '2', 'name' => 'banana'],
            ['_id' => '3', 'name' => 'Cherry'],
        ]);

        self::assertCount(1, iterator_to_array($this->collection->find(['name' => ['$regex' => '^App']])));
        self::assertCount(1, iterator_to_array($this->collection->find(['name' => ['$regex' => 'ana']])));
        self::assertCount(1, iterator_to_array($this->collection->find(['name' => ['$regex' => '^apple', '$options' => 'i']])));
    }

    public function testAllAndSize(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'tags' => ['foo', 'bar']],
            ['_id' => '2', 'tags' => ['foo', 'baz']],
            ['_id' => '3', 'tags' => ['bar']],
        ]);

        self::assertCount(1, iterator_to_array($this->collection->find(['tags' => ['$all' => ['foo', 'bar']]])));
        self::assertCount(1, iterator_to_array($this->collection->find(['tags' => ['$size' => 1]])));
        self::assertCount(2, iterator_to_array($this->collection->find(['tags' => ['$size' => 2]])));
    }

    public function testAddToSet(): void
    {
        $this->collection->insertOne(['_id' => '1', 'tags' => ['foo']]);

        $this->collection->updateOne(['_id' => '1'], ['$addToSet' => ['tags' => 'bar']]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $tags = (array)$doc['tags'];
        sort($tags);
        self::assertEquals(['bar', 'foo'], $tags);

        $this->collection->updateOne(['_id' => '1'], ['$addToSet' => ['tags' => 'foo']]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $tags = (array)$doc['tags'];
        sort($tags);
        self::assertEquals(['bar', 'foo'], $tags); // Should still be ['bar', 'foo']
    }

    public function testPop(): void
    {
        $this->collection->insertOne(['_id' => '1', 'tags' => ['a', 'b', 'c']]);

        $this->collection->updateOne(['_id' => '1'], ['$pop' => ['tags' => 1]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals(['a', 'b'], (array)$doc['tags']);

        $this->collection->updateOne(['_id' => '1'], ['$pop' => ['tags' => -1]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals(['b'], (array)$doc['tags']);
    }

    public function testType(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'v' => 'string'],
            ['_id' => '2', 'v' => 42],
            ['_id' => '3', 'v' => []],
            ['_id' => '4', 'v' => (object)['object' => 'foo']],
            ['_id' => '5', 'v' => true],
            ['_id' => '6', 'v' => null],
        ]);

        $docs = iterator_to_array($this->collection->find(['v' => ['$type' => 'string']]));
        self::assertCount(1, $docs, 'Failed for type string: ' . json_encode($docs));
        self::assertCount(1, iterator_to_array($this->collection->find(['v' => ['$type' => 'number']])), 'Failed for type number');
        self::assertCount(1, iterator_to_array($this->collection->find(['v' => ['$type' => 'array']])), 'Failed for type array');
        self::assertCount(1, iterator_to_array($this->collection->find(['v' => ['$type' => 'object']])), 'Failed for type object');
        self::assertCount(1, iterator_to_array($this->collection->find(['v' => ['$type' => 'bool']])), 'Failed for type bool');
        self::assertCount(1, iterator_to_array($this->collection->find(['v' => ['$type' => 'null']])), 'Failed for type null');
    }

    public function testMod(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'v' => 10],
            ['_id' => '2', 'v' => 11],
            ['_id' => '3', 'v' => 12],
        ]);

        self::assertCount(2, iterator_to_array($this->collection->find(['v' => ['$mod' => [2, 0]]])));
        self::assertCount(1, iterator_to_array($this->collection->find(['v' => ['$mod' => [2, 1]]])));
    }

    public function testMoreAccumulators(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'category' => 'A', 'amount' => 10],
            ['_id' => '2', 'category' => 'B', 'amount' => 20],
            ['_id' => '3', 'category' => 'A', 'amount' => 30],
        ]);

        $pipeline = [
            [
                '$group' => [
                    '_id' => '$category',
                    'avg' => ['$avg' => '$amount'],
                    'min' => ['$min' => '$amount'],
                    'max' => ['$max' => '$amount'],
                    'first' => ['$first' => '$amount'],
                    'last' => ['$last' => '$amount'],
                ],
            ],
            ['$sort' => ['_id' => 1]],
        ];

        $result = $this->collection->aggregate($pipeline);
        $docs = array_values(iterator_to_array($result));

        self::assertCount(2, $docs);
        self::assertEquals('A', $docs[0]['_id']);
        self::assertEquals(20, $docs[0]['avg']);
        self::assertEquals(10, $docs[0]['min']);
        self::assertEquals(30, $docs[0]['max']);
        self::assertEquals(10, $docs[0]['first']);
        self::assertEquals(30, $docs[0]['last']);
    }

    public function testLookup(): void
    {
        $database = $this->getDatabase();
        $orders = $database->getCollection('orders');
        $products = $database->getCollection('products');

        $orders->drop();
        $products->drop();

        $products->insertMany([
            ['_id' => 'p1', 'name' => 'Laptop'],
            ['_id' => 'p2', 'name' => 'Mouse'],
        ]);

        $orders->insertMany([
            ['_id' => 'o1', 'pid' => 'p1', 'qty' => 1],
            ['_id' => 'o2', 'pid' => 'p2', 'qty' => 2],
            ['_id' => 'o3', 'pid' => 'p1', 'qty' => 3],
        ]);

        $pipeline = [
            [
                '$lookup' => [
                    'from' => 'products',
                    'localField' => 'pid',
                    'foreignField' => '_id',
                    'as' => 'product_details',
                ],
            ],
            ['$sort' => ['_id' => 1]],
        ];

        $result = $orders->aggregate($pipeline);
        $docs = array_values(iterator_to_array($result));

        self::assertCount(3, $docs);
        self::assertEquals('o1', $docs[0]['_id']);
        self::assertCount(1, $docs[0]['product_details']);
        self::assertEquals('Laptop', $docs[0]['product_details'][0]['name']);

        self::assertEquals('o2', $docs[1]['_id']);
        self::assertCount(1, $docs[1]['product_details']);
        self::assertEquals('Mouse', $docs[1]['product_details'][0]['name']);
    }

    public function testCurrentDate(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo']);
        $this->collection->updateOne(['_id' => '1'], ['$currentDate' => ['lastModified' => true]]);

        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertArrayHasKey('lastModified', $doc);
        self::assertNotEmpty($doc['lastModified']);
    }

    public function testBitOperator(): void
    {
        $this->collection->insertOne(['_id' => '1', 'v' => 10]); // 1010 in binary

        // AND: 1010 & 1100 (12) = 1000 (8)
        $this->collection->updateOne(['_id' => '1'], ['$bit' => ['v' => ['and' => 12]]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals(8, $doc['v']);

        // OR: 1000 | 0101 (5) = 1101 (13)
        $this->collection->updateOne(['_id' => '1'], ['$bit' => ['v' => ['or' => 5]]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals(13, $doc['v']);

        // XOR: 1101 ^ 0111 (7) = 1010 (10)
        $this->collection->updateOne(['_id' => '1'], ['$bit' => ['v' => ['xor' => 7]]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals(10, $doc['v']);
    }

    public function testBulkWrite(): void
    {
        $result = $this->collection->bulkWrite([
            ['insertOne' => [['_id' => '1', 'v' => 1]]],
            ['insertOne' => [['_id' => '2', 'v' => 2]]],
            ['updateOne' => [['_id' => '1'], ['$set' => ['v' => 10]]]],
            ['deleteOne' => [['_id' => '2']]],
        ]);

        self::assertEquals(2, $result->getInsertedCount());
        self::assertEquals(1, $result->getMatchedCount());
        self::assertEquals(1, $result->getModifiedCount());
        self::assertEquals(1, $result->getDeletedCount());
        self::assertEquals(['1', '2'], $result->getInsertedIds());
        self::assertTrue($result->isAcknowledged());

        self::assertEquals(1, $this->collection->countDocuments());
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals(10, $doc['v']);
    }

    public function testUpdateMany(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'name' => 'foo', 'active' => false],
            ['_id' => '2', 'name' => 'bar', 'active' => false],
            ['_id' => '3', 'name' => 'baz', 'active' => true],
        ]);

        $result = $this->collection->updateMany(['active' => false], ['$set' => ['active' => true]]);

        self::assertEquals(2, $result->getMatchedCount());
        self::assertEquals(2, $result->getModifiedCount());

        self::assertEquals(3, $this->collection->countDocuments(['active' => true]));
    }

    public function testDeleteMany(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'name' => 'foo'],
            ['_id' => '2', 'name' => 'foo'],
            ['_id' => '3', 'name' => 'bar'],
        ]);

        $result = $this->collection->deleteMany(['name' => 'foo']);

        self::assertEquals(2, $result->getDeletedCount());

        self::assertEquals(1, $this->collection->countDocuments());
    }

    public function testFindWithLimitAndSkip(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'v' => 1],
            ['_id' => '2', 'v' => 2],
            ['_id' => '3', 'v' => 3],
            ['_id' => '4', 'v' => 4],
            ['_id' => '5', 'v' => 5],
        ]);

        $result = $this->collection->find([], ['sort' => ['v' => 1], 'limit' => 2, 'skip' => 1]);
        $docs = array_values(iterator_to_array($result));

        self::assertCount(2, $docs);
        self::assertEquals(2, $docs[0]['v']);
        self::assertEquals(3, $docs[1]['v']);
    }

    public function testGetInsertedId(): void
    {
        $result = $this->collection->insertOne(['_id' => '1', 'name' => 'foo']);
        self::assertEquals('1', $result->getInsertedId());
    }

    public function testImplicitId(): void
    {
        $result = $this->collection->insertOne(['name' => 'foo']);
        $id = $result->getInsertedId();
        self::assertNotNull($id);

        $doc = $this->collection->findOne(['_id' => $id]);
        self::assertNotNull($doc);
        self::assertEquals('foo', $doc['name']);
        self::assertEquals($id, $doc['_id']);
    }

    public function testImplicitIdMany(): void
    {
        $result = $this->collection->insertMany([
            ['name' => 'foo'],
            ['name' => 'bar'],
        ]);

        self::assertEquals(2, $result->getInsertedCount());
        $insertedIds = $result->getInsertedIds();
        self::assertCount(2, $insertedIds);

        self::assertEquals(2, $this->collection->countDocuments());

        $docs = iterator_to_array($this->collection->find());
        self::assertCount(2, $docs);
        self::assertArrayHasKey('_id', $docs[0]);
        self::assertArrayHasKey('_id', $docs[1]);
        self::assertNotEquals($docs[0]['_id'], $docs[1]['_id']);

        self::assertContains((string)$docs[0]['_id'], array_map(static fn ($id) => (string)$id, $insertedIds));
        self::assertContains((string)$docs[1]['_id'], array_map(static fn ($id) => (string)$id, $insertedIds));
    }

    public function testAggregateWithLimitAndSkip(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'v' => 1],
            ['_id' => '2', 'v' => 2],
            ['_id' => '3', 'v' => 3],
            ['_id' => '4', 'v' => 4],
            ['_id' => '5', 'v' => 5],
        ]);

        $pipeline = [
            ['$sort' => ['v' => 1]],
            ['$skip' => 2],
            ['$limit' => 2],
        ];

        $result = $this->collection->aggregate($pipeline);
        $docs = array_values(iterator_to_array($result));

        self::assertCount(2, $docs);
        self::assertEquals(3, $docs[0]['v']);
        self::assertEquals(4, $docs[1]['v']);
    }

    public function testIndexManagement(): void
    {
        $this->collection->createIndex(['name' => 1], ['unique' => true, 'name' => 'custom_idx']);
        $indexes = iterator_to_array($this->collection->listIndexes());

        $names = array_map(static fn (IndexInfo|MongoIndexInfo $index) => $index->getName(), $indexes);
        self::assertContains('custom_idx', $names);

        // Find the created index
        $customIndex = null;
        foreach ($indexes as $index) {
            if ($index->getName() === 'custom_idx') {
                $customIndex = $index;
                break;
            }
        }

        self::assertNotNull($customIndex);
        self::assertTrue($customIndex->isUnique());
        self::assertEquals(['name' => 1], $customIndex->getKey());

        $this->collection->dropIndex('custom_idx');
        $indexes = $this->collection->listIndexes();
        $names = array_map(static fn (IndexInfo|MongoIndexInfo $index) => $index->getName(), iterator_to_array($indexes));
        self::assertNotContains('custom_idx', $names);
    }

    public function testIndexWithNestedFields(): void
    {
        $this->collection->createIndex(['address.street' => 1, 'address.city' => -1], ['name' => 'address_idx']);
        $indexes = iterator_to_array($this->collection->listIndexes());

        $addressIndex = null;
        foreach ($indexes as $index) {
            if ($index->getName() === 'address_idx') {
                $addressIndex = $index;
                break;
            }
        }

        self::assertNotNull($addressIndex, 'address_idx should exist');

        $keys = $addressIndex->getKey();
        self::assertEquals(['address.street' => 1, 'address.city' => -1], $keys);

        $this->collection->dropIndex('address_idx');
    }

    public function testListCollections(): void
    {
        $this->collection->insertOne(['foo' => 'bar']);
        $database = $this->getDatabase();
        $collections = $database->listCollections();

        $names = array_map(static fn ($col) => (is_array($col) ? $col['name'] : $col->getName()), is_array($collections) ? $collections : iterator_to_array($collections));
        self::assertContains('items', $names);
    }

    public function testRenameCollection(): void
    {
        $this->collection->insertOne(['foo' => 'bar']);
        $database = $this->getDatabase();
        $database->renameCollection('items', 'items_new');

        $collections = $database->listCollections();
        $names = array_map(static fn ($col) => (is_array($col) ? $col['name'] : $col->getName()), is_array($collections) ? $collections : iterator_to_array($collections));

        self::assertContains('items_new', $names);
        self::assertNotContains('items', $names);

        // Cleanup
        $database->renameCollection('items_new', 'items');
    }

    public function testElemMatch(): void
    {
        $this->collection->insertMany([
            [
                '_id' => '1',
                'results' => [
                    ['product' => 'abc', 'score' => 10],
                    ['product' => 'xyz', 'score' => 5],
                ],
            ],
            [
                '_id' => '2',
                'results' => [
                    ['product' => 'abc', 'score' => 8],
                    ['product' => 'xyz', 'score' => 7],
                ],
            ],
        ]);

        $docs = iterator_to_array($this->collection->find([
            'results' => [
                '$elemMatch' => ['product' => 'abc', 'score' => ['$gte' => 10]],
            ],
        ]));

        self::assertCount(1, $docs);
        self::assertEquals('1', $docs[0]['_id']);

        $docs = iterator_to_array($this->collection->find([
            'results' => [
                '$elemMatch' => ['product' => 'abc', 'score' => ['$gt' => 5]],
            ],
        ]));
        self::assertCount(2, $docs);
    }

    public function testListDatabases(): void
    {
        $this->collection->insertOne(['foo' => 'bar']);
        $client = $this->getClient();
        $databases = $client->listDatabases();

        $names = array_map(static fn ($db) => (is_array($db) ? $db['name'] : $db->getName()), is_array($databases) ? $databases : iterator_to_array($databases));
        self::assertContains('test', $names);
    }

    public function testPushWithEach(): void
    {
        $this->collection->insertOne(['_id' => '1', 'tags' => ['foo']]);

        $this->collection->updateOne(['_id' => '1'], ['$push' => ['tags' => ['$each' => ['bar', 'baz']]]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals(['foo', 'bar', 'baz'], (array)$doc['tags']);
    }

    public function testNestedFilter(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'metadata' => ['logins' => 5, 'active' => true]],
            ['_id' => '2', 'metadata' => ['logins' => 15, 'active' => true]],
            ['_id' => '3', 'metadata' => ['logins' => 5, 'active' => false]],
        ]);

        self::assertCount(1, iterator_to_array($this->collection->find(['metadata.logins' => 5, 'metadata.active' => false])));
        self::assertCount(1, iterator_to_array($this->collection->find(['metadata.logins' => ['$gt' => 10]])));
    }

    public function testComplexAggregate(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'status' => 'A', 'amount' => 10, 'tags' => ['foo']],
            ['_id' => '2', 'status' => 'A', 'amount' => 20, 'tags' => ['bar']],
            ['_id' => '3', 'status' => 'B', 'amount' => 30, 'tags' => ['foo', 'bar']],
            ['_id' => '4', 'status' => 'A', 'amount' => 40, 'tags' => ['baz']],
        ]);

        $pipeline = [
            ['$match' => ['status' => 'A']],
            ['$unwind' => '$tags'],
            [
                '$group' => [
                    '_id' => '$tags',
                    'total' => ['$sum' => '$amount'],
                ],
            ],
            ['$sort' => ['total' => -1]],
        ];

        $result = $this->collection->aggregate($pipeline);
        $docs = array_values(iterator_to_array($result));

        self::assertCount(3, $docs);
        self::assertEquals('baz', $docs[0]['_id']);
        self::assertEquals(40, $docs[0]['total']);
        self::assertEquals('bar', $docs[1]['_id']);
        self::assertEquals(20, $docs[1]['total']);
        self::assertEquals('foo', $docs[2]['_id']);
        self::assertEquals(10, $docs[2]['total']);
    }

    public function testCombinedUpdate(): void
    {
        $this->collection->insertOne([
            '_id' => '1',
            'name' => 'foo',
            'score' => 10,
            'tags' => ['a'],
        ]);

        $this->collection->updateOne(
            ['_id' => '1'],
            [
                '$set' => ['name' => 'bar'],
                '$inc' => ['score' => 5],
                '$push' => ['tags' => 'b'],
            ],
        );

        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals('bar', $doc['name']);
        self::assertEquals(15, $doc['score']);
        self::assertEquals(['a', 'b'], (array)$doc['tags']);
    }

    public function testIncCreatesField(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo']);

        $this->collection->updateOne(['_id' => '1'], ['$inc' => ['counter' => 2]]);
        $doc = $this->collection->findOne(['_id' => '1']);

        self::assertEquals(2, $doc['counter']);
    }

    public function testMulOperator(): void
    {
        $this->collection->insertOne(['_id' => '1', 'value' => 5]);

        $this->collection->updateOne(['_id' => '1'], ['$mul' => ['value' => 3]]);
        $doc = $this->collection->findOne(['_id' => '1']);

        self::assertEquals(15, $doc['value']);
    }

    public function testSetOnInsert(): void
    {
        $this->collection->updateOne(
            ['_id' => '1'],
            [
                '$set' => ['name' => 'first'],
                '$setOnInsert' => ['created' => 'yes'],
            ],
            ['upsert' => true],
        );

        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals('first', $doc['name']);
        self::assertEquals('yes', $doc['created']);

        $this->collection->updateOne(
            ['_id' => '1'],
            [
                '$set' => ['name' => 'second'],
                '$setOnInsert' => ['created' => 'no'],
            ],
            ['upsert' => true],
        );

        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals('second', $doc['name']);
        self::assertEquals('yes', $doc['created']);
    }

    public function testRegexOptions(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'text' => "bar\nfoo"],
            ['_id' => '2', 'text' => "a\nc"],
        ]);

        self::assertCount(1, iterator_to_array($this->collection->find(['text' => ['$regex' => '^foo', '$options' => 'm']]))); // multiline
        self::assertCount(1, iterator_to_array($this->collection->find(['text' => ['$regex' => 'a.*c', '$options' => 's']]))); // dotall
    }

    public function testInvalidTopLevelOperatorThrows(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo']);

        $this->expectException(RuntimeException::class);
        $this->collection->find(['$unknown' => ['name' => 'foo']]);
    }

    public function testInvalidInOperatorThrows(): void
    {
        $this->collection->insertOne(['_id' => '1', 'age' => 20]);

        $this->expectException(RuntimeException::class);
        $this->collection->find(['age' => ['$in' => 'not-an-array']]);
    }

    public function testInvalidModOperatorThrows(): void
    {
        $this->collection->insertOne(['_id' => '1', 'v' => 10]);

        $this->expectException(RuntimeException::class);
        $this->collection->find(['v' => ['$mod' => [2]]]);
    }

    public function testInvalidElemMatchThrows(): void
    {
        $this->collection->insertOne(['_id' => '1', 'tags' => ['a', 'b']]);

        $this->expectException(RuntimeException::class);
        $this->collection->find(['tags' => ['$elemMatch' => 'not-an-array']]);
    }

    public function testUpdateWithoutOperatorsThrows(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo']);

        $this->expectException($this->collection instanceof RangoCollection ? RuntimeException::class : InvalidArgumentException::class);
        $this->collection->updateOne(['_id' => '1'], []);
    }

    public function testUpsertWithoutIdThrows(): void
    {
        if ($this->collection instanceof RangoCollection) {
            $this->expectException(RuntimeException::class);
            $this->collection->updateOne(['name' => 'foo'], ['$set' => ['name' => 'bar']], ['upsert' => true]);

            return;
        }

        $result = $this->collection->updateOne(['name' => 'foo'], ['$set' => ['name' => 'bar']], ['upsert' => true]);
        self::assertEquals(1, $result->getUpsertedCount());
        self::assertNotNull($result->getUpsertedId());
    }

    public function testInvalidBitOperatorThrows(): void
    {
        $this->collection->insertOne(['_id' => '1', 'v' => 10]);

        $this->expectException(RuntimeException::class);
        $this->collection->updateOne(['_id' => '1'], ['$bit' => ['v' => ['invalid' => 1]]]);
    }

    public function testElemMatchComplex(): void
    {
        $this->collection->insertMany([
            [
                '_id' => '1',
                'grades' => [
                    ['val' => 80, 'mean' => 75],
                    ['val' => 90, 'mean' => 85],
                ],
            ],
            [
                '_id' => '2',
                'grades' => [
                    ['val' => 85, 'mean' => 90],
                ],
            ],
        ]);

        $docs = iterator_to_array($this->collection->find([
            'grades' => [
                '$elemMatch' => [
                    'val' => ['$gt' => 85],
                    'mean' => ['$gt' => 80],
                ],
            ],
        ]));

        self::assertCount(1, $docs);
        self::assertEquals('1', $docs[0]['_id']);
    }

    public function testLookupComplex(): void
    {
        $database = $this->getDatabase();
        $orders = $database->getCollection('orders');
        $products = $database->getCollection('products');
        $categories = $database->getCollection('categories');

        $orders->drop();
        $products->drop();
        $categories->drop();

        $categories->insertMany([
            ['_id' => 'c1', 'name' => 'Electronics'],
            ['_id' => 'c2', 'name' => 'Accessories'],
        ]);

        $products->insertMany([
            ['_id' => 'p1', 'name' => 'Laptop', 'cid' => 'c1'],
            ['_id' => 'p2', 'name' => 'Mouse', 'cid' => 'c2'],
        ]);

        $orders->insertMany([
            ['_id' => 'o1', 'pid' => 'p1', 'qty' => 1],
        ]);

        $pipeline = [
            [
                '$lookup' => [
                    'from' => 'products',
                    'localField' => 'pid',
                    'foreignField' => '_id',
                    'as' => 'product',
                ],
            ],
            ['$unwind' => '$product'],
            [
                '$lookup' => [
                    'from' => 'categories',
                    'localField' => 'product.cid',
                    'foreignField' => '_id',
                    'as' => 'category',
                ],
            ],
            ['$unwind' => '$category'],
            ['$sort' => ['_id' => 1]],
        ];

        $result = $orders->aggregate($pipeline);
        $docs = array_values(iterator_to_array($result));

        self::assertCount(1, $docs);
        self::assertEquals('o1', $docs[0]['_id']);
        self::assertEquals('Laptop', $docs[0]['product']['name']);
        self::assertEquals('Electronics', $docs[0]['category']['name']);
    }

    public function testNestedSortAndProjection(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'metadata' => ['order' => 2, 'label' => 'B']],
            ['_id' => '2', 'metadata' => ['order' => 1, 'label' => 'A']],
            ['_id' => '3', 'metadata' => ['order' => 3, 'label' => 'C']],
        ]);

        $result = $this->collection->find([], [
            'sort' => ['metadata.order' => 1],
            'projection' => ['metadata.label' => 1, '_id' => 1],
        ]);
        $docs = array_values(iterator_to_array($result));

        self::assertCount(3, $docs);
        self::assertEquals('2', $docs[0]['_id']);
        self::assertEquals('A', $docs[0]['metadata']['label']);
        self::assertArrayNotHasKey('order', $docs[0]['metadata']);
    }

    public function testUpdateWithDotNotation(): void
    {
        $this->collection->insertOne(['_id' => '1', 'profile' => ['name' => 'foo', 'stats' => ['score' => 1]]]);

        $this->collection->updateOne(
            ['_id' => '1'],
            [
                '$set' => ['profile.name' => 'bar'],
                '$inc' => ['profile.stats.score' => 2],
            ],
        );

        $doc = $this->collection->findOne(['_id' => '1']);
        self::assertEquals('bar', $doc['profile']['name']);
        self::assertEquals(3, $doc['profile']['stats']['score']);
    }

    public function testDeeplyNestedUpdate(): void
    {
        $this->collection->insertOne(['_id' => '1']);

        $this->collection->updateOne(
            ['_id' => '1'],
            [
                '$set' => ['a.b.c.d.e.f' => 'deep'],
                '$inc' => ['a.b.c.d.e.counter' => 2],
            ],
        );

        $doc = $this->collection->findOne(['_id' => '1']);

        self::assertEquals('deep', $doc['a']['b']['c']['d']['e']['f']);
        self::assertEquals(2, $doc['a']['b']['c']['d']['e']['counter']);
    }

    public function testUnsetWithDotNotation(): void
    {
        $this->collection->insertOne(['_id' => '1', 'profile' => ['name' => 'foo', 'stats' => ['score' => 1]]]);

        $this->collection->updateOne(['_id' => '1'], ['$unset' => ['profile.stats.score' => true]]);
        $doc = $this->collection->findOne(['_id' => '1']);

        self::assertArrayNotHasKey('score', $doc['profile']['stats']);
    }

    public function testRenameWithDotNotation(): void
    {
        $this->collection->insertOne(['_id' => '1', 'profile' => ['name' => 'foo', 'stats' => ['score' => 1]]]);

        $this->collection->updateOne(['_id' => '1'], ['$rename' => ['profile.stats.score' => 'profile.stats.points']]);
        $doc = $this->collection->findOne(['_id' => '1']);

        self::assertArrayNotHasKey('score', $doc['profile']['stats']);
        self::assertEquals(1, $doc['profile']['stats']['points']);
    }

    public function testNestedProjectionInclude(): void
    {
        $this->collection->insertOne([
            '_id' => '1',
            'profile' => ['name' => 'foo', 'stats' => ['score' => 5, 'level' => 2]],
            'meta' => ['active' => true],
        ]);

        $doc = $this->collection->findOne(['_id' => '1'], ['projection' => ['profile.stats.level' => 1]]);

        self::assertArrayHasKey('_id', $doc);
        self::assertArrayHasKey('profile', $doc);
        self::assertArrayHasKey('stats', $doc['profile']);
        self::assertArrayHasKey('level', $doc['profile']['stats']);
        self::assertArrayNotHasKey('score', $doc['profile']['stats']);
        self::assertArrayNotHasKey('meta', $doc);
    }

    public function testProjectionExcludesNestedAndTopLevel(): void
    {
        $this->collection->insertOne([
            '_id' => '1',
            'profile' => ['name' => 'foo', 'stats' => ['score' => 5, 'level' => 2]],
            'meta' => ['active' => true],
        ]);

        $doc = $this->collection->findOne(['_id' => '1'], [
            'projection' => ['profile.stats.score' => 0, 'meta' => 0],
        ]);

        self::assertArrayHasKey('profile', $doc);
        self::assertArrayHasKey('stats', $doc['profile']);
        self::assertArrayNotHasKey('score', $doc['profile']['stats']);
        self::assertEquals(2, $doc['profile']['stats']['level']);
        self::assertArrayNotHasKey('meta', $doc);
    }

    public function testSelectAliases(): void
    {
        $client = $this->getClient();
        $db = $client->selectDatabase('test');
        self::assertNotNull($db);

        $col = $db->selectCollection('items');

        $col->insertOne(['_id' => 'alias-test', 'name' => 'alias']);
        $doc = $col->findOne(['_id' => 'alias-test']);

        self::assertEquals('alias', $doc['name']);
    }
}
