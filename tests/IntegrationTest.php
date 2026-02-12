<?php

declare(strict_types=1);

namespace Patchlevel\Rango\Tests;

use MongoDB\Client as MongoDbClient;
use MongoDB\Collection as MongoDbCollection;
use MongoDB\Database as MongoDbDatabase;
use Patchlevel\Rango\Client as RangoClient;
use Patchlevel\Rango\Collection as RangoCollection;
use Patchlevel\Rango\Database as RangoDatabase;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_values;
use function is_array;
use function iterator_to_array;
use function sort;

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
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo']);

        $result = $this->collection->find(['name' => 'foo']);
        $docs = iterator_to_array($result);

        $this->assertCount(1, $docs);
        $this->assertEquals('1', $docs[0]['_id']);
        $this->assertEquals('foo', $docs[0]['name']);
    }

    public function testInsertManyAndCount(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'name' => 'foo'],
            ['_id' => '2', 'name' => 'bar'],
        ]);

        $this->assertEquals(2, $this->collection->countDocuments());
        $this->assertEquals(1, $this->collection->countDocuments(['name' => 'foo']));
    }

    public function testFindOne(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo']);

        $doc = $this->collection->findOne(['_id' => '1']);

        $this->assertNotNull($doc);
        $this->assertEquals('1', $doc['_id']);
        $this->assertEquals('foo', $doc['name']);
    }

    public function testUpdateOne(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo']);
        $this->collection->updateOne(['_id' => '1'], ['$set' => ['name' => 'bar']]);

        $doc = $this->collection->findOne(['_id' => '1']);

        $this->assertNotNull($doc);
        $this->assertEquals('bar', $doc['name']);
    }

    public function testDeleteOne(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo']);
        $this->assertEquals(1, $this->collection->countDocuments());

        $this->collection->deleteOne(['_id' => '1']);
        $this->assertEquals(0, $this->collection->countDocuments());
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
        $this->assertEquals('2', $docs[0]['_id']);
        $this->assertEquals('1', $docs[1]['_id']);
        $this->assertEquals('3', $docs[2]['_id']);

        $result = $this->collection->find([], ['sort' => ['name' => -1]]);
        $docs = array_values(iterator_to_array($result));
        $this->assertEquals('3', $docs[0]['_id']);
        $this->assertEquals('1', $docs[1]['_id']);
        $this->assertEquals('2', $docs[2]['_id']);
    }

    public function testProjection(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo', 'age' => 42]);

        $doc = $this->collection->findOne(['_id' => '1'], ['projection' => ['name' => 1]]);
        $this->assertArrayHasKey('name', $doc);
        $this->assertArrayHasKey('_id', $doc);
        $this->assertArrayNotHasKey('age', $doc);

        $doc = $this->collection->findOne(['_id' => '1'], ['projection' => ['age' => 0]]);
        $this->assertArrayHasKey('name', $doc);
        $this->assertArrayHasKey('_id', $doc);
        $this->assertArrayNotHasKey('age', $doc);
    }

    public function testReplaceOne(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo', 'age' => 42]);
        $this->collection->replaceOne(['_id' => '1'], ['_id' => '1', 'name' => 'bar']);

        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals('bar', $doc['name']);
        $this->assertArrayNotHasKey('age', $doc);
    }

    public function testIncAndUnset(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo', 'age' => 42]);

        $this->collection->updateOne(['_id' => '1'], ['$inc' => ['age' => 1]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals(43, $doc['age']);

        $this->collection->updateOne(['_id' => '1'], ['$unset' => ['name' => '']]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertArrayNotHasKey('name', $doc);
        $this->assertEquals(43, $doc['age']);
    }

    public function testComparisonOperators(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'age' => 20],
            ['_id' => '2', 'age' => 30],
            ['_id' => '3', 'age' => 40],
        ]);

        $this->assertCount(2, iterator_to_array($this->collection->find(['age' => ['$gt' => 20]])));
        $this->assertCount(3, iterator_to_array($this->collection->find(['age' => ['$gte' => 20]])));
        $this->assertCount(1, iterator_to_array($this->collection->find(['age' => ['$lt' => 30]])));
        $this->assertCount(2, iterator_to_array($this->collection->find(['age' => ['$lte' => 30]])));
        $this->assertCount(2, iterator_to_array($this->collection->find(['age' => ['$ne' => 30]])));
        $this->assertCount(2, iterator_to_array($this->collection->find(['age' => ['$in' => [20, 40]]])));
        $this->assertCount(1, iterator_to_array($this->collection->find(['age' => ['$nin' => [20, 40]]])));
    }

    public function testLogicalOperators(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'name' => 'foo', 'age' => 20],
            ['_id' => '2', 'name' => 'bar', 'age' => 30],
            ['_id' => '3', 'name' => 'foo', 'age' => 40],
        ]);

        $this->assertCount(1, iterator_to_array($this->collection->find([
            '$and' => [
                ['name' => 'foo'],
                ['age' => ['$gt' => 20]],
            ],
        ])));

        $this->assertCount(2, iterator_to_array($this->collection->find([
            '$or' => [
                ['name' => 'bar'],
                ['age' => 40],
            ],
        ])));

        $this->assertCount(0, iterator_to_array($this->collection->find([
            '$nor' => [
                ['name' => 'foo'],
                ['age' => 30],
            ],
        ])));

        $this->assertCount(2, iterator_to_array($this->collection->find([
            'name' => ['$not' => ['$eq' => 'bar']],
        ])));
    }

    public function testExists(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'name' => 'foo'],
            ['_id' => '2', 'age' => 30],
        ]);

        $this->assertCount(1, iterator_to_array($this->collection->find(['name' => ['$exists' => true]])));
        $this->assertCount(1, iterator_to_array($this->collection->find(['name' => ['$exists' => false]])));
    }

    public function testUpsert(): void
    {
        $this->collection->updateOne(['_id' => '1'], ['$set' => ['name' => 'foo']], ['upsert' => true]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertNotNull($doc);
        $this->assertEquals('foo', $doc['name']);

        $this->collection->replaceOne(['_id' => '2'], ['_id' => '2', 'name' => 'bar'], ['upsert' => true]);
        $doc = $this->collection->findOne(['_id' => '2']);
        $this->assertNotNull($doc);
        $this->assertEquals('bar', $doc['name']);
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

        $this->assertCount(2, $docs);
        $this->assertEquals(['name' => 'foo'], $docs[0]);
        $this->assertEquals(['name' => 'foo'], $docs[1]);
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

        $this->assertCount(3, $docs);
        $this->assertEquals('a', $docs[0]['items']);
        $this->assertEquals('b', $docs[1]['items']);
        $this->assertEquals('c', $docs[2]['items']);
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

        $this->assertCount(2, $docs);
        $this->assertEquals('A', $docs[0]['_id']);
        $this->assertEquals(40, $docs[0]['total']);
        $this->assertEquals(2, $docs[0]['count']);

        $this->assertEquals('B', $docs[1]['_id']);
        $this->assertEquals(20, $docs[1]['total']);
        $this->assertEquals(1, $docs[1]['count']);
    }

    public function testFindOneAndModify(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo', 'counter' => 10]);

        // findOneAndUpdate
        $old = $this->collection->findOneAndUpdate(['_id' => '1'], ['$inc' => ['counter' => 1]]);
        $this->assertEquals(10, $old['counter']);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals(11, $doc['counter']);

        // findOneAndReplace
        $old = $this->collection->findOneAndReplace(['_id' => '1'], ['_id' => '1', 'name' => 'bar']);
        $this->assertEquals('foo', $old['name']);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals('bar', $doc['name']);
        $this->assertArrayNotHasKey('counter', $doc);

        // findOneAndDelete
        $old = $this->collection->findOneAndDelete(['_id' => '1']);
        $this->assertEquals('bar', $old['name']);
        $this->assertEquals(0, $this->collection->countDocuments());
    }

    public function testRenameMinMax(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo', 'val' => 50]);

        $this->collection->updateOne(['_id' => '1'], ['$rename' => ['name' => 'nickname']]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertArrayNotHasKey('name', $doc);
        $this->assertEquals('foo', $doc['nickname']);

        $this->collection->updateOne(['_id' => '1'], ['$min' => ['val' => 25]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals(25, $doc['val']);

        $this->collection->updateOne(['_id' => '1'], ['$min' => ['val' => 75]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals(25, $doc['val']); // Should stay 25

        $this->collection->updateOne(['_id' => '1'], ['$max' => ['val' => 100]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals(100, $doc['val']);

        $this->collection->updateOne(['_id' => '1'], ['$max' => ['val' => 50]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals(100, $doc['val']); // Should stay 100
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

        $this->assertEquals(['A', 'B'], $values);
    }

    public function testPushAndPull(): void
    {
        $this->collection->insertOne(['_id' => '1', 'tags' => ['foo']]);

        $this->collection->updateOne(['_id' => '1'], ['$push' => ['tags' => 'bar']]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals(['foo', 'bar'], (array)$doc['tags']);

        $this->collection->updateOne(['_id' => '1'], ['$pull' => ['tags' => 'foo']]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals(['bar'], (array)$doc['tags']);
    }

    public function testRegex(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'name' => 'Apple'],
            ['_id' => '2', 'name' => 'banana'],
            ['_id' => '3', 'name' => 'Cherry'],
        ]);

        $this->assertCount(1, iterator_to_array($this->collection->find(['name' => ['$regex' => '^App']])));
        $this->assertCount(1, iterator_to_array($this->collection->find(['name' => ['$regex' => 'ana']])));
        $this->assertCount(1, iterator_to_array($this->collection->find(['name' => ['$regex' => '^apple', '$options' => 'i']])));
    }

    public function testAllAndSize(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'tags' => ['foo', 'bar']],
            ['_id' => '2', 'tags' => ['foo', 'baz']],
            ['_id' => '3', 'tags' => ['bar']],
        ]);

        $this->assertCount(1, iterator_to_array($this->collection->find(['tags' => ['$all' => ['foo', 'bar']]])));
        $this->assertCount(1, iterator_to_array($this->collection->find(['tags' => ['$size' => 1]])));
        $this->assertCount(2, iterator_to_array($this->collection->find(['tags' => ['$size' => 2]])));
    }

    public function testAddToSet(): void
    {
        $this->collection->insertOne(['_id' => '1', 'tags' => ['foo']]);

        $this->collection->updateOne(['_id' => '1'], ['$addToSet' => ['tags' => 'bar']]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $tags = (array)$doc['tags'];
        sort($tags);
        $this->assertEquals(['bar', 'foo'], $tags);

        $this->collection->updateOne(['_id' => '1'], ['$addToSet' => ['tags' => 'foo']]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $tags = (array)$doc['tags'];
        sort($tags);
        $this->assertEquals(['bar', 'foo'], $tags); // Should still be ['bar', 'foo']
    }

    public function testPop(): void
    {
        $this->collection->insertOne(['_id' => '1', 'tags' => ['a', 'b', 'c']]);

        $this->collection->updateOne(['_id' => '1'], ['$pop' => ['tags' => 1]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals(['a', 'b'], (array)$doc['tags']);

        $this->collection->updateOne(['_id' => '1'], ['$pop' => ['tags' => -1]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals(['b'], (array)$doc['tags']);
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
        $this->assertCount(1, $docs, 'Failed for type string: ' . json_encode($docs));
        $this->assertCount(1, iterator_to_array($this->collection->find(['v' => ['$type' => 'number']])), 'Failed for type number');
        $this->assertCount(1, iterator_to_array($this->collection->find(['v' => ['$type' => 'array']])), 'Failed for type array');
        $this->assertCount(1, iterator_to_array($this->collection->find(['v' => ['$type' => 'object']])), 'Failed for type object');
        $this->assertCount(1, iterator_to_array($this->collection->find(['v' => ['$type' => 'bool']])), 'Failed for type bool');
        $this->assertCount(1, iterator_to_array($this->collection->find(['v' => ['$type' => 'null']])), 'Failed for type null');
    }

    public function testMod(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'v' => 10],
            ['_id' => '2', 'v' => 11],
            ['_id' => '3', 'v' => 12],
        ]);

        $this->assertCount(2, iterator_to_array($this->collection->find(['v' => ['$mod' => [2, 0]]])));
        $this->assertCount(1, iterator_to_array($this->collection->find(['v' => ['$mod' => [2, 1]]])));
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

        $this->assertCount(2, $docs);
        $this->assertEquals('A', $docs[0]['_id']);
        $this->assertEquals(20, $docs[0]['avg']);
        $this->assertEquals(10, $docs[0]['min']);
        $this->assertEquals(30, $docs[0]['max']);
        $this->assertEquals(10, $docs[0]['first']);
        $this->assertEquals(30, $docs[0]['last']);
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

        $this->assertCount(3, $docs);
        $this->assertEquals('o1', $docs[0]['_id']);
        $this->assertCount(1, $docs[0]['product_details']);
        $this->assertEquals('Laptop', $docs[0]['product_details'][0]['name']);

        $this->assertEquals('o2', $docs[1]['_id']);
        $this->assertCount(1, $docs[1]['product_details']);
        $this->assertEquals('Mouse', $docs[1]['product_details'][0]['name']);
    }

    public function testCurrentDate(): void
    {
        $this->collection->insertOne(['_id' => '1', 'name' => 'foo']);
        $this->collection->updateOne(['_id' => '1'], ['$currentDate' => ['lastModified' => true]]);

        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertArrayHasKey('lastModified', $doc);
        $this->assertNotEmpty($doc['lastModified']);
    }

    public function testBitOperator(): void
    {
        $this->collection->insertOne(['_id' => '1', 'v' => 10]); // 1010 in binary

        // AND: 1010 & 1100 (12) = 1000 (8)
        $this->collection->updateOne(['_id' => '1'], ['$bit' => ['v' => ['and' => 12]]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals(8, $doc['v']);

        // OR: 1000 | 0101 (5) = 1101 (13)
        $this->collection->updateOne(['_id' => '1'], ['$bit' => ['v' => ['or' => 5]]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals(13, $doc['v']);

        // XOR: 1101 ^ 0111 (7) = 1010 (10)
        $this->collection->updateOne(['_id' => '1'], ['$bit' => ['v' => ['xor' => 7]]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals(10, $doc['v']);
    }

    public function testBulkWrite(): void
    {
        $this->collection->bulkWrite([
            ['insertOne' => [['_id' => '1', 'v' => 1]]],
            ['insertOne' => [['_id' => '2', 'v' => 2]]],
            ['updateOne' => [['_id' => '1'], ['$set' => ['v' => 10]]]],
            ['deleteOne' => [['_id' => '2']]],
        ]);

        $this->assertEquals(1, $this->collection->countDocuments());
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals(10, $doc['v']);
    }

    public function testUpdateMany(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'name' => 'foo', 'active' => false],
            ['_id' => '2', 'name' => 'bar', 'active' => false],
            ['_id' => '3', 'name' => 'baz', 'active' => true],
        ]);

        $this->collection->updateMany(['active' => false], ['$set' => ['active' => true]]);

        $this->assertEquals(3, $this->collection->countDocuments(['active' => true]));
    }

    public function testDeleteMany(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'name' => 'foo'],
            ['_id' => '2', 'name' => 'foo'],
            ['_id' => '3', 'name' => 'bar'],
        ]);

        $this->collection->deleteMany(['name' => 'foo']);

        $this->assertEquals(1, $this->collection->countDocuments());
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

        $this->assertCount(2, $docs);
        $this->assertEquals(2, $docs[0]['v']);
        $this->assertEquals(3, $docs[1]['v']);
    }

    public function testGetInsertedId(): void
    {
        $result = $this->collection->insertOne(['_id' => '1', 'name' => 'foo']);
        $this->assertEquals('1', $result->getInsertedId());
    }

    public function testImplicitId(): void
    {
        $result = $this->collection->insertOne(['name' => 'foo']);
        $id = $result->getInsertedId();
        $this->assertNotNull($id);

        $doc = $this->collection->findOne(['_id' => $id]);
        $this->assertNotNull($doc);
        $this->assertEquals('foo', $doc['name']);
        $this->assertEquals($id, $doc['_id']);
    }

    public function testImplicitIdMany(): void
    {
        $this->collection->insertMany([
            ['name' => 'foo'],
            ['name' => 'bar'],
        ]);

        $this->assertEquals(2, $this->collection->countDocuments());

        $docs = iterator_to_array($this->collection->find());
        $this->assertCount(2, $docs);
        $this->assertArrayHasKey('_id', $docs[0]);
        $this->assertArrayHasKey('_id', $docs[1]);
        $this->assertNotEquals($docs[0]['_id'], $docs[1]['_id']);
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

        $this->assertCount(2, $docs);
        $this->assertEquals(3, $docs[0]['v']);
        $this->assertEquals(4, $docs[1]['v']);
    }

    public function testIndexManagement(): void
    {
        $this->collection->createIndex(['name' => 1], ['unique' => true, 'name' => 'custom_idx']);
        $indexes = $this->collection->listIndexes();

        $names = array_map(static fn ($index) => (is_array($index) ? $index['name'] : $index->getName()), is_array($indexes) ? $indexes : iterator_to_array($indexes));
        $this->assertContains('custom_idx', $names);

        $this->collection->dropIndex('custom_idx');
        $indexes = $this->collection->listIndexes();
        $names = array_map(static fn ($index) => (is_array($index) ? $index['name'] : $index->getName()), is_array($indexes) ? $indexes : iterator_to_array($indexes));
        $this->assertNotContains('custom_idx', $names);
    }

    public function testListCollections(): void
    {
        $this->collection->insertOne(['foo' => 'bar']);
        $database = $this->getDatabase();
        $collections = $database->listCollections();

        $names = array_map(static fn ($col) => (is_array($col) ? $col['name'] : $col->getName()), is_array($collections) ? $collections : iterator_to_array($collections));
        $this->assertContains('items', $names);
    }

    public function testRenameCollection(): void
    {
        $this->collection->insertOne(['foo' => 'bar']);
        $database = $this->getDatabase();
        $database->renameCollection('items', 'items_new');

        $collections = $database->listCollections();
        $names = array_map(static fn ($col) => (is_array($col) ? $col['name'] : $col->getName()), is_array($collections) ? $collections : iterator_to_array($collections));

        $this->assertContains('items_new', $names);
        $this->assertNotContains('items', $names);

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

        $this->assertCount(1, $docs);
        $this->assertEquals('1', $docs[0]['_id']);

        $docs = iterator_to_array($this->collection->find([
            'results' => [
                '$elemMatch' => ['product' => 'abc', 'score' => ['$gt' => 5]],
            ],
        ]));
        $this->assertCount(2, $docs);
    }

    public function testListDatabases(): void
    {
        $this->collection->insertOne(['foo' => 'bar']);
        $client = $this->getClient();
        $databases = $client->listDatabases();

        $names = array_map(static fn ($db) => (is_array($db) ? $db['name'] : $db->getName()), is_array($databases) ? $databases : iterator_to_array($databases));
        $this->assertContains('test', $names);
    }

    public function testPushWithEach(): void
    {
        $this->collection->insertOne(['_id' => '1', 'tags' => ['foo']]);

        $this->collection->updateOne(['_id' => '1'], ['$push' => ['tags' => ['$each' => ['bar', 'baz']]]]);
        $doc = $this->collection->findOne(['_id' => '1']);
        $this->assertEquals(['foo', 'bar', 'baz'], (array)$doc['tags']);
    }

    public function testNestedFilter(): void
    {
        $this->collection->insertMany([
            ['_id' => '1', 'metadata' => ['logins' => 5, 'active' => true]],
            ['_id' => '2', 'metadata' => ['logins' => 15, 'active' => true]],
            ['_id' => '3', 'metadata' => ['logins' => 5, 'active' => false]],
        ]);

        $this->assertCount(1, iterator_to_array($this->collection->find(['metadata.logins' => 5, 'metadata.active' => false])));
        $this->assertCount(1, iterator_to_array($this->collection->find(['metadata.logins' => ['$gt' => 10]])));
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
            ['$group' => [
                '_id' => '$tags',
                'total' => ['$sum' => '$amount'],
            ]],
            ['$sort' => ['total' => -1]],
        ];

        $result = $this->collection->aggregate($pipeline);
        $docs = array_values(iterator_to_array($result));

        $this->assertCount(3, $docs);
        $this->assertEquals('baz', $docs[0]['_id']);
        $this->assertEquals(40, $docs[0]['total']);
        $this->assertEquals('bar', $docs[1]['_id']);
        $this->assertEquals(20, $docs[1]['total']);
        $this->assertEquals('foo', $docs[2]['_id']);
        $this->assertEquals(10, $docs[2]['total']);
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
        $this->assertEquals('bar', $doc['name']);
        $this->assertEquals(15, $doc['score']);
        $this->assertEquals(['a', 'b'], (array)$doc['tags']);
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

        $this->assertCount(1, $docs);
        $this->assertEquals('1', $docs[0]['_id']);
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

        $this->assertCount(1, $docs);
        $this->assertEquals('o1', $docs[0]['_id']);
        $this->assertEquals('Laptop', $docs[0]['product']['name']);
        $this->assertEquals('Electronics', $docs[0]['category']['name']);
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

        $this->assertCount(3, $docs);
        $this->assertEquals('2', $docs[0]['_id']);
        $this->assertEquals('A', $docs[0]['metadata']['label']);
        $this->assertArrayNotHasKey('order', $docs[0]['metadata']);
    }

    public function testSelectAliases(): void
    {
        $client = $this->getClient();
        $db = $client->selectDatabase('test');
        $this->assertNotNull($db);

        $col = $db->selectCollection('items');
        $this->assertNotNull($col);

        $col->insertOne(['_id' => 'alias-test', 'name' => 'alias']);
        $doc = $col->findOne(['_id' => 'alias-test']);

        $this->assertEquals('alias', $doc['name']);
    }
}
