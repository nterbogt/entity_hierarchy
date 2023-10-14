<?php

namespace Drupal\Tests\entity_hierarchy\Unit;

use Drupal\entity_hierarchy\Storage\Record;
use Drupal\entity_hierarchy\Storage\RecordCollection;
use Drupal\Tests\UnitTestCase;

/**
 * @group entity_hierarchy
 *
 * @coversDefaultClass \Drupal\entity_hierarchy\Storage\RecordCollection
 */
class RecordCollectionTest extends UnitTestCase {

  protected RecordCollection $collection;

  protected function setUp(): void {
    parent::setUp();

    $records = [];
    // Purposefully out of order. Put them back in order with a sort on ID.
    $record_data = [
      ['test', 1, 0, 0, NULL],
      ['test', 7, 1, 0, NULL],
      ['test', 2, 0, 1, 1],
      ['test', 5, 1, 1, 1],
      ['test', 3, 0, 2, 2],
      ['test', 4, 1, 2, 2],
      ['test', 6, 0, 2, 5],
      ['test', 8, 0, 1, 7],
    ];
    foreach ($record_data as $data) {
      list($type, $id, $weight, $depth, $target_id) = $data;
      $records[] = Record::create($type, $id, $weight, $depth, $target_id);
    }

    $this->collection = new RecordCollection($records);
  }

  public function testCount() {
    $this->assertCount(8, $this->collection);
  }

  public function testMap() {
    $ids = $this->collection->map(fn (Record $record) => $record->getId());
    $expected = range(1, 8);
    $this->assertEqualsCanonicalizing($expected, $ids);
  }

  public function testFilter() {
    $this->collection->filter(fn(Record $record) => $record->getDepth() !== 2);
    $this->assertCount(5, $this->collection);
  }

}
