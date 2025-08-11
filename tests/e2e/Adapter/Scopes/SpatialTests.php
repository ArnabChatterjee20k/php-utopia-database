<?php

namespace Tests\E2E\Adapter\Scopes;

use Exception;
use Throwable;
use Utopia\Database\Database;
use Utopia\Database\Document;
use Utopia\Database\Exception as DatabaseException;
use Utopia\Database\Exception\Duplicate as DuplicateException;
use Utopia\Database\Exception\Limit as LimitException;
use Utopia\Database\Exception\Query as QueryException;
use Utopia\Database\Helpers\ID;
use Utopia\Database\Helpers\Permission;
use Utopia\Database\Helpers\Role;
use Utopia\Database\Query;
use Utopia\Database\Validator\Index;

trait SpatialTests
{
    public function testBasicAttributeCreation(): void
    {
        $database = $this->getDatabase();

        if (!$database->getAdapter()->getSupportForSpatialAttributes()) {
            $this->markTestSkipped('Spatial attributes not supported by this adapter');
        }

        // Create collection
        $result = $database->createCollection('test_basic');
        $this->assertInstanceOf(\Utopia\Database\Document::class, $result);

        // Test spatial attribute creation
        $this->assertEquals(true, $database->createAttribute('test_basic', 'point', Database::VAR_POINT, 0, true));
        $this->assertEquals(true, $database->createAttribute('test_basic', 'linestring', Database::VAR_LINESTRING, 0, true));
        $this->assertEquals(true, $database->createAttribute('test_basic', 'polygon', Database::VAR_POLYGON, 0, true));
        $this->assertEquals(true, $database->createAttribute('test_basic', 'geometry', Database::VAR_GEOMETRY, 0, true));

        $collection = $database->getCollection('test_basic');
        $attributes = $collection->getAttribute('attributes', []);

        $this->assertCount(4, $attributes);
        $this->assertEquals('point', $attributes[0]['$id']);
        $this->assertEquals(Database::VAR_POINT, $attributes[0]['type']);
        $this->assertEquals('linestring', $attributes[1]['$id']);
        $this->assertEquals(Database::VAR_LINESTRING, $attributes[1]['type']);
    }

    public function testSpatialAttributeSupport(): void
    {
        $database = $this->getDatabase();
        
        // Check if the adapter supports spatial attributes
        $this->assertIsBool($database->getAdapter()->getSupportForSpatialAttributes());
        
        // Skip tests if spatial attributes are not supported
        if (!$database->getAdapter()->getSupportForSpatialAttributes()) {
            $this->markTestSkipped('Spatial attributes not supported by this adapter');
        }
    }

    public function testCreateSpatialAttributes(): void
    {
        $database = $this->getDatabase();

        if (!$database->getAdapter()->getSupportForSpatialAttributes()) {
            $this->markTestSkipped('Spatial attributes not supported by this adapter');
        }

        $result = $database->createCollection('spatial_attributes');
        $this->assertInstanceOf(\Utopia\Database\Document::class, $result);

        // Create spatial attributes of different types
        $this->assertEquals(true, $database->createAttribute('spatial_attributes', 'point', Database::VAR_POINT, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_attributes', 'linestring', Database::VAR_LINESTRING, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_attributes', 'polygon', Database::VAR_POLYGON, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_attributes', 'geometry', Database::VAR_GEOMETRY, 0, true));

        $collection = $database->getCollection('spatial_attributes');
        $attributes = $collection->getAttribute('attributes', []);

        $this->assertCount(4, $attributes);

        foreach ($attributes as $attribute) {
            $this->assertInstanceOf(\Utopia\Database\Document::class, $attribute);
            $this->assertContains($attribute->getAttribute('type'), [
                Database::VAR_POINT,
                Database::VAR_LINESTRING,
                Database::VAR_POLYGON,
                Database::VAR_GEOMETRY
            ]);
        }
    }

    public function testCreateSpatialIndexes(): void
    {
        $database = $this->getDatabase();

        if (!$database->getAdapter()->getSupportForSpatialAttributes()) {
            $this->markTestSkipped('Spatial attributes not supported by this adapter');
        }

        $result = $database->createCollection('spatial_indexes');
        $this->assertInstanceOf(\Utopia\Database\Document::class, $result);

        // Create spatial attributes
        $this->assertEquals(true, $database->createAttribute('spatial_indexes', 'point', Database::VAR_POINT, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_indexes', 'linestring', Database::VAR_LINESTRING, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_indexes', 'polygon', Database::VAR_POLYGON, 0, true));

        // Create spatial indexes
        $this->assertEquals(true, $database->createIndex('spatial_indexes', 'point_spatial', Database::INDEX_SPATIAL, ['point']));
        $this->assertEquals(true, $database->createIndex('spatial_indexes', 'linestring_spatial', Database::INDEX_SPATIAL, ['linestring']));
        $this->assertEquals(true, $database->createIndex('spatial_indexes', 'polygon_spatial', Database::INDEX_SPATIAL, ['polygon']));

        $collection = $database->getCollection('spatial_indexes');
        $indexes = $collection->getAttribute('indexes', []);

        $this->assertCount(3, $indexes);

        foreach ($indexes as $index) {
            $this->assertInstanceOf(\Utopia\Database\Document::class, $index);
            $this->assertEquals(Database::INDEX_SPATIAL, $index->getAttribute('type'));
        }
    }

    public function testSpatialDataInsertAndRetrieve(): void
    {
        $database = $this->getDatabase();

        if (!$database->getAdapter()->getSupportForSpatialAttributes()) {
            $this->markTestSkipped('Spatial attributes not supported by this adapter');
        }

        $result = $database->createCollection('spatial_data');
        $this->assertInstanceOf(\Utopia\Database\Document::class, $result);

        // Create spatial attributes and a name attribute
        $this->assertEquals(true, $database->createAttribute('spatial_data', 'point', Database::VAR_POINT, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_data', 'linestring', Database::VAR_LINESTRING, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_data', 'polygon', Database::VAR_POLYGON, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_data', 'name', Database::VAR_STRING, 255, true));

        // Create spatial indexes
        $this->assertEquals(true, $database->createIndex('spatial_data', 'point_spatial', Database::INDEX_SPATIAL, ['point']));
        $this->assertEquals(true, $database->createIndex('spatial_data', 'linestring_spatial', Database::INDEX_SPATIAL, ['linestring']));
        $this->assertEquals(true, $database->createIndex('spatial_data', 'polygon_spatial', Database::INDEX_SPATIAL, ['polygon']));

        // Insert documents with spatial data
        $doc1 = $database->createDocument('spatial_data', new \Utopia\Database\Document([
            '$id' => 'doc1',
            '$permissions' => [Permission::read(Role::any())],
            'name' => 'Point Document',
            'point' => [10.0, 20.0],
            'linestring' => [[0.0, 0.0], [1.0, 1.0], [2.0, 2.0]],
            'polygon' => [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]]
        ]));

        $doc2 = $database->createDocument('spatial_data', new \Utopia\Database\Document([
            '$id' => 'doc2',
            '$permissions' => [Permission::read(Role::any())],
            'name' => 'Second Document',
            'point' => [15.0, 25.0],
            'linestring' => [[5.0, 5.0], [6.0, 6.0], [7.0, 7.0]],
            'polygon' => [[5.0, 5.0], [15.0, 5.0], [15.0, 15.0], [5.0, 15.0], [5.0, 5.0]]
        ]));

        $this->assertInstanceOf(\Utopia\Database\Document::class, $doc1);
        $this->assertInstanceOf(\Utopia\Database\Document::class, $doc2);

        // Retrieve and verify spatial data
        $retrieved1 = $database->getDocument('spatial_data', 'doc1');
        $retrieved2 = $database->getDocument('spatial_data', 'doc2');

        $this->assertEquals([10.0, 20.0], $retrieved1->getAttribute('point'));
        $this->assertEquals([[0.0, 0.0], [1.0, 1.0], [2.0, 2.0]], $retrieved1->getAttribute('linestring'));
        $this->assertEquals([[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]], $retrieved1->getAttribute('polygon'));

        $this->assertEquals([15.0, 25.0], $retrieved2->getAttribute('point'));
        $this->assertEquals([[5.0, 5.0], [6.0, 6.0], [7.0, 7.0]], $retrieved2->getAttribute('linestring'));
        $this->assertEquals([[5.0, 5.0], [15.0, 5.0], [15.0, 15.0], [5.0, 15.0], [5.0, 5.0]], $retrieved2->getAttribute('polygon'));
    }

    public function testSpatialQueries(): void
    {
        $database = $this->getDatabase();
        
        // Skip tests if spatial attributes are not supported
        if (!$database->getAdapter()->getSupportForSpatialAttributes()) {
            $this->markTestSkipped('Spatial attributes not supported by this adapter');
        }

        $database->createCollection('spatial_queries');

        // Create spatial attributes
        $this->assertEquals(true, $database->createAttribute('spatial_queries', 'point', Database::VAR_POINT, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_queries', 'polygon', Database::VAR_POLYGON, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_queries', 'name', Database::VAR_STRING, 255, true));

        // Create spatial indexes
        $this->assertEquals(true, $database->createIndex('spatial_queries', 'point_spatial', Database::INDEX_SPATIAL, ['point']));
        $this->assertEquals(true, $database->createIndex('spatial_queries', 'polygon_spatial', Database::INDEX_SPATIAL, ['polygon']));

        // Insert test documents
        $document1 = new Document([
            '$id' => ID::unique(),
            '$permissions' => [
                Permission::read(Role::any()),
                Permission::create(Role::any()),
                Permission::update(Role::any()),
                Permission::delete(Role::any()),
            ],
            'point' => [5, 5],
            'polygon' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]],
            'name' => 'Center Point'
        ]);

        $document2 = new Document([
            '$id' => ID::unique(),
            '$permissions' => [
                Permission::read(Role::any()),
                Permission::create(Role::any()),
                Permission::update(Role::any()),
                Permission::delete(Role::any()),
            ],
            'point' => [15, 15],
            'polygon' => [[[10, 10], [20, 10], [20, 20], [10, 20], [10, 10]]],
            'name' => 'Outside Point'
        ]);

        $this->assertInstanceOf(\Utopia\Database\Document::class, $database->createDocument('spatial_queries', $document1));
        $this->assertInstanceOf(\Utopia\Database\Document::class, $database->createDocument('spatial_queries', $document2));

        // Test spatial queries
        // Test contains query
        $containsQuery = Query::contains('polygon', [[5, 5]]);
        $containsResults = $database->find('spatial_queries', [$containsQuery]);
        $this->assertCount(1, $containsResults);
        $this->assertEquals('Center Point', $containsResults[0]->getAttribute('name'));

        // Test intersects query
        $intersectsQuery = Query::intersects('polygon', [[5, 5]]); // Simplified to single point
        $intersectsResults = $database->find('spatial_queries', [$intersectsQuery]);
        $this->assertCount(1, $intersectsResults); // Point [5,5] only intersects with Document 1's polygon
        $this->assertEquals('Center Point', $intersectsResults[0]->getAttribute('name'));

        // Test equals query
        $equalsQuery = Query::equal('point', [[5, 5]]);
        $equalsResults = $database->find('spatial_queries', [$equalsQuery]);
        $this->assertCount(1, $equalsResults);
        $this->assertEquals('Center Point', $equalsResults[0]->getAttribute('name'));
    }

    public function testSpatialQueryNegations(): void
    {
        $database = $this->getDatabase();
        
        // Skip tests if spatial attributes are not supported
        if (!$database->getAdapter()->getSupportForSpatialAttributes()) {
            $this->markTestSkipped('Spatial attributes not supported by this adapter');
        }

        $database->createCollection('spatial_negations');

        // Create spatial attributes
        $this->assertEquals(true, $database->createAttribute('spatial_negations', 'point', Database::VAR_POINT, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_negations', 'polygon', Database::VAR_POLYGON, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_negations', 'name', Database::VAR_STRING, 255, true));

        // Create spatial indexes
        $this->assertEquals(true, $database->createIndex('spatial_negations', 'point_spatial', Database::INDEX_SPATIAL, ['point']));
        $this->assertEquals(true, $database->createIndex('spatial_negations', 'polygon_spatial', Database::INDEX_SPATIAL, ['polygon']));

        // Insert test documents
        $document1 = new Document([
            '$id' => ID::unique(),
            '$permissions' => [
                Permission::read(Role::any()),
                Permission::create(Role::any()),
                Permission::update(Role::any()),
                Permission::delete(Role::any()),
            ],
            'point' => [5, 5],
            'polygon' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]],
            'name' => 'Document 1'
        ]);

        $document2 = new Document([
            '$id' => ID::unique(),
            '$permissions' => [
                Permission::read(Role::any()),
                Permission::create(Role::any()),
                Permission::update(Role::any()),
                Permission::delete(Role::any()),
            ],
            'point' => [15, 15],
            'polygon' => [[[10, 10], [20, 10], [20, 20], [10, 20], [10, 10]]],
            'name' => 'Document 2'
        ]);

        $this->assertInstanceOf(\Utopia\Database\Document::class, $database->createDocument('spatial_negations', $document1));
        $this->assertInstanceOf(\Utopia\Database\Document::class, $database->createDocument('spatial_negations', $document2));

        // Test notContains query
        $notContainsQuery = Query::notContains('polygon', [[15, 15]]);
        $notContainsResults = $database->find('spatial_negations', [$notContainsQuery]);
        $this->assertCount(1, $notContainsResults);
        $this->assertEquals('Document 1', $notContainsResults[0]->getAttribute('name'));

        // Test notEquals query
        $notEqualsQuery = Query::spatialNotEquals('point', [[5, 5]]); // Use spatialNotEquals for spatial data
        $notEqualsResults = $database->find('spatial_negations', [$notEqualsQuery]);
        $this->assertCount(1, $notEqualsResults);
        $this->assertEquals('Document 2', $notEqualsResults[0]->getAttribute('name'));

        // Test notIntersects query
        $notIntersectsQuery = Query::notIntersects('polygon', [[[25, 25], [35, 35]]]);
        $notIntersectsResults = $database->find('spatial_negations', [$notIntersectsQuery]);
        $this->assertCount(2, $notIntersectsResults);
    }

    public function testSpatialQueryCombinations(): void
    {
        $database = $this->getDatabase();
        
        // Skip tests if spatial attributes are not supported
        if (!$database->getAdapter()->getSupportForSpatialAttributes()) {
            $this->markTestSkipped('Spatial attributes not supported by this adapter');
        }

        $database->createCollection('spatial_combinations');

        // Create spatial attributes
        $this->assertEquals(true, $database->createAttribute('spatial_combinations', 'point', Database::VAR_POINT, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_combinations', 'polygon', Database::VAR_POLYGON, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_combinations', 'name', Database::VAR_STRING, 255, true));

        // Create spatial indexes
        $this->assertEquals(true, $database->createIndex('spatial_combinations', 'point_spatial', Database::INDEX_SPATIAL, ['point']));
        $this->assertEquals(true, $database->createIndex('spatial_combinations', 'polygon_spatial', Database::INDEX_SPATIAL, ['polygon']));

        // Insert test documents
        $document1 = new Document([
            '$id' => ID::unique(),
            '$permissions' => [
                Permission::read(Role::any()),
                Permission::create(Role::any()),
                Permission::update(Role::any()),
                Permission::delete(Role::any()),
            ],
            'point' => [5, 5],
            'polygon' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]],
            'name' => 'Center Document'
        ]);

        $document2 = new Document([
            '$id' => ID::unique(),
            '$permissions' => [
                Permission::read(Role::any()),
                Permission::create(Role::any()),
                Permission::update(Role::any()),
                Permission::delete(Role::any()),
            ],
            'point' => [15, 15],
            'polygon' => [[[10, 10], [20, 10], [20, 20], [10, 20], [10, 10]]],
            'name' => 'Outside Document'
        ]);

        $this->assertInstanceOf(\Utopia\Database\Document::class, $database->createDocument('spatial_combinations', $document1));
        $this->assertInstanceOf(\Utopia\Database\Document::class, $database->createDocument('spatial_combinations', $document2));

        // Test AND combination
        $pointQuery = Query::spatialEquals('point', [[5, 5]]);
        $polygonQuery = Query::spatialContains('polygon', [[5, 5]]);
        $andQuery = Query::and([$pointQuery, $polygonQuery]);
        
        $andResults = $database->find('spatial_combinations', [$andQuery]);
        $this->assertCount(1, $andResults);
        $this->assertEquals('Center Document', $andResults[0]->getAttribute('name'));

        // Test OR combination
        $pointQuery2 = Query::spatialEquals('point', [[5, 5]]);
        $pointQuery3 = Query::spatialEquals('point', [[15, 15]]);
        $orQuery = Query::or([$pointQuery2, $pointQuery3]);
        
        $orResults = $database->find('spatial_combinations', [$orQuery]);
        $this->assertCount(2, $orResults);
    }

    public function testSpatialDataUpdate(): void
    {
        $database = $this->getDatabase();
        
        // Skip tests if spatial attributes are not supported
        if (!$database->getAdapter()->getSupportForSpatialAttributes()) {
            $this->markTestSkipped('Spatial attributes not supported by this adapter');
        }

        $database->createCollection('spatial_update');

        // Create spatial attributes
        $this->assertEquals(true, $database->createAttribute('spatial_update', 'point', Database::VAR_POINT, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_update', 'polygon', Database::VAR_POLYGON, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_update', 'name', Database::VAR_STRING, 255, true));

        // Create spatial indexes
        $this->assertEquals(true, $database->createIndex('spatial_update', 'point_spatial', Database::INDEX_SPATIAL, ['point']));
        $this->assertEquals(true, $database->createIndex('spatial_update', 'polygon_spatial', Database::INDEX_SPATIAL, ['polygon']));

        // Insert test document
        $document = new Document([
            '$id' => ID::unique(),
            '$permissions' => [
                Permission::read(Role::any()),
                Permission::create(Role::any()),
                Permission::update(Role::any()),
                Permission::delete(Role::any()),
            ],
            'point' => [5, 5],
            'polygon' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]],
            'name' => 'Original Document'
        ]);

        $this->assertInstanceOf(\Utopia\Database\Document::class, $database->createDocument('spatial_update', $document));

        // Update spatial data
        $document->setAttribute('point', [25, 25]);
        $document->setAttribute('polygon', [[[20, 20], [30, 20], [30, 30], [20, 30], [20, 20]]]);
        $document->setAttribute('name', 'Updated Document');

        $this->assertInstanceOf(\Utopia\Database\Document::class, $database->updateDocument('spatial_update', $document->getId(), $document));

        // Retrieve and verify updated data
        $updatedDocument = $database->getDocument('spatial_update', $document->getId());
        
        $this->assertEquals([25, 25], $updatedDocument->getAttribute('point'));
        $this->assertEquals([[20, 20], [30, 20], [30, 30], [20, 30], [20, 20]], $updatedDocument->getAttribute('polygon'));
        $this->assertEquals('Updated Document', $updatedDocument->getAttribute('name'));
    }

    public function testSpatialIndexDeletion(): void
    {
        $database = $this->getDatabase();
        
        // Skip tests if spatial attributes are not supported
        if (!$database->getAdapter()->getSupportForSpatialAttributes()) {
            $this->markTestSkipped('Spatial attributes not supported by this adapter');
        }

        $database->createCollection('spatial_index_deletion');

        // Create spatial attributes
        $this->assertEquals(true, $database->createAttribute('spatial_index_deletion', 'point', Database::VAR_POINT, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_index_deletion', 'polygon', Database::VAR_POLYGON, 0, true));

        // Create spatial indexes
        $this->assertEquals(true, $database->createIndex('spatial_index_deletion', 'point_spatial', Database::INDEX_SPATIAL, ['point']));
        $this->assertEquals(true, $database->createIndex('spatial_index_deletion', 'polygon_spatial', Database::INDEX_SPATIAL, ['polygon']));

        $collection = $database->getCollection('spatial_index_deletion');
        $this->assertCount(2, $collection->getAttribute('indexes'));

        // Delete spatial indexes
        $this->assertEquals(true, $database->deleteIndex('spatial_index_deletion', 'point_spatial'));
        $this->assertEquals(true, $database->deleteIndex('spatial_index_deletion', 'polygon_spatial'));

        $collection = $database->getCollection('spatial_index_deletion');
        $this->assertCount(0, $collection->getAttribute('indexes'));
    }

    public function testSpatialDataValidation(): void
    {
        $database = $this->getDatabase();
        
        // Skip tests if spatial attributes are not supported
        if (!$database->getAdapter()->getSupportForSpatialAttributes()) {
            $this->markTestSkipped('Spatial attributes not supported by this adapter');
        }

        $database->createCollection('spatial_validation');

        // Create spatial attributes
        $this->assertEquals(true, $database->createAttribute('spatial_validation', 'point', Database::VAR_POINT, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_validation', 'linestring', Database::VAR_LINESTRING, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_validation', 'polygon', Database::VAR_POLYGON, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_validation', 'name', Database::VAR_STRING, 255, true));

        // Test valid POINT data
        $validPointDoc = new Document([
            '$id' => ID::unique(),
            '$permissions' => [
                Permission::read(Role::any()),
                Permission::create(Role::any()),
                Permission::update(Role::any()),
                Permission::delete(Role::any()),
            ],
            'point' => [10, 20],
            'linestring' => [[0, 0], [10, 10]],
            'polygon' => [[0, 0], [5, 0], [5, 5], [0, 5], [0, 0]],
            'name' => 'Valid Point'
        ]);

        $this->assertInstanceOf(\Utopia\Database\Document::class, $database->createDocument('spatial_validation', $validPointDoc));

        // Test valid LINESTRING data
        $validLineDoc = new Document([
            '$id' => ID::unique(),
            '$permissions' => [
                Permission::read(Role::any()),
                Permission::create(Role::any()),
                Permission::update(Role::any()),
                Permission::delete(Role::any()),
            ],
            'point' => [15, 25],
            'linestring' => [[0, 0], [10, 10], [20, 20]],
            'polygon' => [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]],
            'name' => 'Valid Line'
        ]);

        $this->assertInstanceOf(\Utopia\Database\Document::class, $database->createDocument('spatial_validation', $validLineDoc));

        // Test valid POLYGON data
        $validPolygonDoc = new Document([
            '$id' => ID::unique(),
            '$permissions' => [
                Permission::read(Role::any()),
                Permission::create(Role::any()),
                Permission::update(Role::any()),
                Permission::delete(Role::any()),
            ],
            'point' => [30, 35],
            'linestring' => [[5, 5], [15, 15], [25, 25]],
            'polygon' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]],
            'name' => 'Valid Polygon'
        ]);

        $this->assertInstanceOf(\Utopia\Database\Document::class, $database->createDocument('spatial_validation', $validPolygonDoc));

        // Verify all documents were created
        $allDocs = $database->find('spatial_validation', []);
        $this->assertCount(3, $allDocs);
    }

    public function testSpatialDataCleanup(): void
    {
        $database = $this->getDatabase();
        
        // Skip tests if spatial attributes are not supported
        if (!$database->getAdapter()->getSupportForSpatialAttributes()) {
            $this->markTestSkipped('Spatial attributes not supported by this adapter');
        }

        // Create collection if it doesn't exist
        if (!$database->exists(null, 'spatial_validation')) {
            $database->createCollection('spatial_validation');
        }
        
        $collection = $database->getCollection('spatial_validation');
        $this->assertNotNull($collection);
        
        $database->deleteCollection($collection->getId());
        
        $this->assertTrue(true, 'Cleanup completed');
    }

    public function testSpatialBulkOperations(): void
    {
        $database = $this->getDatabase();
        
        // Skip tests if spatial attributes are not supported
        if (!$database->getAdapter()->getSupportForSpatialAttributes()) {
            $this->markTestSkipped('Spatial attributes not supported by this adapter');
        }

        $database->createCollection('spatial_bulk');

        // Create spatial attributes
        $this->assertEquals(true, $database->createAttribute('spatial_bulk', 'point', Database::VAR_POINT, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_bulk', 'polygon', Database::VAR_POLYGON, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_bulk', 'name', Database::VAR_STRING, 255, true));

        // Create spatial indexes
        $this->assertEquals(true, $database->createIndex('spatial_bulk', 'point_spatial', Database::INDEX_SPATIAL, ['point']));
        $this->assertEquals(true, $database->createIndex('spatial_bulk', 'polygon_spatial', Database::INDEX_SPATIAL, ['polygon']));

        // Test bulk create with spatial data
        $documents = [
            new Document([
                '$id' => ID::unique(),
                '$permissions' => [
                    Permission::read(Role::any()),
                    Permission::create(Role::any()),
                    Permission::update(Role::any()),
                    Permission::delete(Role::any()),
                ],
                'point' => [1, 1],
                'polygon' => [[[0, 0], [5, 0], [5, 5], [0, 5], [0, 0]]],
                'name' => 'Bulk Document 1'
            ]),
            new Document([
                '$id' => ID::unique(),
                '$permissions' => [
                    Permission::read(Role::any()),
                    Permission::create(Role::any()),
                    Permission::update(Role::any()),
                    Permission::delete(Role::any()),
                ],
                'point' => [2, 2],
                'polygon' => [[[5, 5], [10, 5], [10, 10], [5, 10], [5, 5]]],
                'name' => 'Bulk Document 2'
            ]),
            new Document([
                '$id' => ID::unique(),
                '$permissions' => [
                    Permission::read(Role::any()),
                    Permission::create(Role::any()),
                    Permission::update(Role::any()),
                    Permission::delete(Role::any()),
                ],
                'point' => [3, 3],
                'polygon' => [[[10, 10], [15, 10], [15, 15], [10, 15], [10, 10]]],
                'name' => 'Bulk Document 3'
            ])
        ];

        $createdCount = $database->createDocuments('spatial_bulk', $documents);
        $this->assertEquals(3, $createdCount);
        
        // Verify all documents were created with correct spatial data
        $allDocs = $database->find('spatial_bulk', []);
        foreach ($allDocs as $doc) {
            $this->assertInstanceOf(\Utopia\Database\Document::class, $doc);
            $this->assertIsArray($doc->getAttribute('point'));
            $this->assertIsArray($doc->getAttribute('polygon'));
        }

        // Test bulk update with spatial data
        $updateDoc = new Document([
            'point' => [20, 20],
            'polygon' => [[[10, 10], [15, 10], [15, 15], [10, 15], [10, 10]]],
            'name' => 'Updated Document'
        ]);

        $updateResults = $database->updateDocuments('spatial_bulk', $updateDoc, []);
        $this->assertEquals(3, $updateResults);

        // Verify updates were applied
        $updatedAllDocs = $database->find('spatial_bulk', []);
        foreach ($updatedAllDocs as $doc) {
            $this->assertInstanceOf(\Utopia\Database\Document::class, $doc);
            $this->assertEquals('Updated Document', $doc->getAttribute('name'));
            $this->assertEquals([20, 20], $doc->getAttribute('point'));
            $this->assertEquals([[[10, 10], [15, 10], [15, 15], [10, 15], [10, 10]]], $doc->getAttribute('polygon'));
        }

        // Test spatial queries on bulk-created data
        $containsQuery = Query::spatialContains('polygon', [[12, 12]]);
        $containsResults = $database->find('spatial_bulk', [$containsQuery]);
        $this->assertCount(1, $containsResults);
        $this->assertEquals('Updated Document', $containsResults[0]->getAttribute('name'));

        // Test bulk delete
        $docsToDelete = $database->find('spatial_bulk', []);
        $deleteResults = $database->deleteDocuments('spatial_bulk', $docsToDelete);
        $this->assertEquals(3, $deleteResults);

        // Verify all documents were deleted
        $remainingDocs = $database->find('spatial_bulk', []);
        $this->assertCount(0, $remainingDocs);
    }

    public function testSpatialIndividualDelete(): void
    {
        $database = $this->getDatabase();
        
        // Skip tests if spatial attributes are not supported
        if (!$database->getAdapter()->getSupportForSpatialAttributes()) {
            $this->markTestSkipped('Spatial attributes not supported by this adapter');
        }

        $database->createCollection('spatial_individual_delete');

        // Create spatial attributes
        $this->assertEquals(true, $database->createAttribute('spatial_individual_delete', 'point', Database::VAR_POINT, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_individual_delete', 'polygon', Database::VAR_POLYGON, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_individual_delete', 'name', Database::VAR_STRING, 255, true));

        // Create spatial indexes
        $this->assertEquals(true, $database->createIndex('spatial_individual_delete', 'point_spatial', Database::INDEX_SPATIAL, ['point']));
        $this->assertEquals(true, $database->createIndex('spatial_individual_delete', 'polygon_spatial', Database::INDEX_SPATIAL, ['polygon']));

        // Create test document
        $document = new Document([
            '$id' => ID::unique(),
            '$permissions' => [
                Permission::read(Role::any()),
                Permission::create(Role::any()),
                Permission::update(Role::any()),
                Permission::delete(Role::any()),
            ],
            'point' => [25, 25],
            'polygon' => [[[20, 20], [30, 20], [30, 30], [20, 30], [20, 20]]],
            'name' => 'Delete Test Document'
        ]);

        $createdDoc = $database->createDocument('spatial_individual_delete', $document);
        $this->assertInstanceOf(\Utopia\Database\Document::class, $createdDoc);

        // Verify document exists
        $retrievedDoc = $database->getDocument('spatial_individual_delete', $createdDoc->getId());
        $this->assertEquals([25, 25], $retrievedDoc->getAttribute('point'));

        // Test individual delete
        $deleteResult = $database->deleteDocument('spatial_individual_delete', $createdDoc->getId());
        $this->assertTrue($deleteResult);

        // Verify document was deleted
        $deletedDoc = $database->getDocument('spatial_individual_delete', $createdDoc->getId());
        $this->assertTrue($deletedDoc->isEmpty());
    }

    public function testSpatialListDocuments(): void
    {
        $database = $this->getDatabase();
        
        // Skip tests if spatial attributes are not supported
        if (!$database->getAdapter()->getSupportForSpatialAttributes()) {
            $this->markTestSkipped('Spatial attributes not supported by this adapter');
        }

        $database->createCollection('spatial_list');

        // Create spatial attributes
        $this->assertEquals(true, $database->createAttribute('spatial_list', 'point', Database::VAR_POINT, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_list', 'polygon', Database::VAR_POLYGON, 0, true));
        $this->assertEquals(true, $database->createAttribute('spatial_list', 'name', Database::VAR_STRING, 255, true));

        // Create spatial indexes
        $this->assertEquals(true, $database->createIndex('spatial_list', 'point_spatial', Database::INDEX_SPATIAL, ['point']));
        $this->assertEquals(true, $database->createIndex('spatial_list', 'polygon_spatial', Database::INDEX_SPATIAL, ['polygon']));

        // Create multiple test documents
        $documents = [
            new Document([
                '$id' => ID::unique(),
                '$permissions' => [
                    Permission::read(Role::any()),
                    Permission::create(Role::any()),
                    Permission::update(Role::any()),
                    Permission::delete(Role::any()),
                ],
                'point' => [1, 1],
                'polygon' => [[[0, 0], [5, 0], [5, 5], [0, 5], [0, 0]]],
                'name' => 'List Document 1'
            ]),
            new Document([
                '$id' => ID::unique(),
                '$permissions' => [
                    Permission::read(Role::any()),
                    Permission::create(Role::any()),
                    Permission::update(Role::any()),
                    Permission::delete(Role::any()),
                ],
                'point' => [2, 2],
                'polygon' => [[[5, 5], [10, 5], [10, 10], [5, 10], [5, 5]]],
                'name' => 'List Document 2'
            ]),
            new Document([
                '$id' => ID::unique(),
                '$permissions' => [
                    Permission::read(Role::any()),
                    Permission::create(Role::any()),
                    Permission::update(Role::any()),
                    Permission::delete(Role::any()),
                ],
                'point' => [3, 3],
                'polygon' => [[[10, 10], [15, 10], [15, 15], [10, 15], [10, 10]]],
                'name' => 'List Document 3'
            ])
        ];

        foreach ($documents as $doc) {
            $database->createDocument('spatial_list', $doc);
        }

        // Test find without queries (should return all)
        $allDocs = $database->find('spatial_list', [], Database::PERMISSION_READ);
        $this->assertCount(3, $allDocs);

        // Verify spatial data is correctly retrieved
        foreach ($allDocs as $doc) {
            $this->assertInstanceOf(\Utopia\Database\Document::class, $doc);
            $this->assertIsArray($doc->getAttribute('point'));
            $this->assertIsArray($doc->getAttribute('polygon'));
            $this->assertStringContainsString('List Document', $doc->getAttribute('name'));
        }

        // Test find with spatial query
        $containsQuery = Query::spatialContains('polygon', [[2, 2]]);
        $filteredDocs = $database->find('spatial_list', [$containsQuery], Database::PERMISSION_READ);
        $this->assertCount(1, $filteredDocs);
        $this->assertEquals('List Document 1', $filteredDocs[0]->getAttribute('name'));

        // Test pagination
        $paginatedDocs = $database->find('spatial_list', [], Database::PERMISSION_READ, 3);
        $this->assertCount(3, $paginatedDocs);

        $paginatedDocs2 = $database->find('spatial_list', [], Database::PERMISSION_READ, 3, 3);
        $this->assertCount(0, $paginatedDocs2);
    }
} 