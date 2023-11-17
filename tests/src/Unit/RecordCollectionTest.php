<?php

namespace Drupal\Tests\entity_hierarchy\Unit;

use Drupal\entity_hierarchy\Storage\Record;
use Drupal\entity_hierarchy\Storage\RecordCollection;
use Drupal\Tests\UnitTestCase;

/**
 * Test an un-altered record collection.
 *
 * @group entity_hierarchy
 *
 * @coversDefaultClass \Drupal\entity_hierarchy\Storage\RecordCollection
 */
class RecordCollectionTest extends UnitTestCase {

  protected RecordCollection $collection;

  /**
   * Expected weight order. Overridable by subclasses.
   *
   * @var array|int[]
   */
  protected array $expectedWeightOrder = [1, 2, 3, 6, 8, 7, 5, 4];

  /**
   * {@inheritdoc}
   */
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
      [$type, $id, $weight, $depth, $target_id] = $data;
      $records[] = Record::create($type, $id, $weight, $depth, $target_id);
    }

    $this->collection = new RecordCollection($records);
  }

  /**
   * Test the number of records in the collection.
   */
  public function testCount(): void {
    $this->assertCount(8, $this->collection);
  }

  /**
   * Test running a map over all the records.
   */
  public function testMap(): void {
    $ids = $this->collection->map(fn (Record $record) => $record->getId());
    $expected = range(1, 8);
    $this->assertEqualsCanonicalizing($expected, $ids);
  }

  /**
   * Test running a filter over all records.
   */
  public function testFilter(): void {
    $this->collection->filter(fn(Record $record) => $record->getDepth() !== 2);
    $this->assertCount(5, $this->collection);
  }

  /**
   * Test running a sort over all records.
   */
  public function testSortId(): void {
    $ids = $this->collection
      ->sort(fn (Record $a, Record $b) => $a->getId() <=> $b->getId())
      ->map(fn (Record $record) => $record->getId());
    $expected = range(1, 8);
    $this->assertEquals($expected, $ids);
  }

  /**
   * Test running a sort over all records.
   */
  public function testSortWeight(): void {
    $ids = $this->collection
      ->sort()
      ->map(fn (Record $record) => $record->getId());
    $this->assertEquals($this->expectedWeightOrder, $ids);
  }

}
