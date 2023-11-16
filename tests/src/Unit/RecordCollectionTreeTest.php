<?php

namespace Drupal\Tests\entity_hierarchy\Unit;

use Drupal\entity_hierarchy\Storage\Record;

/**
 * Run parent tests across a hierarchical collection.
 *
 * @group entity_hierarchy
 *
 * @coversDefaultClass \Drupal\entity_hierarchy\Storage\RecordCollection
 */
class RecordCollectionTreeTest extends RecordCollectionTest {

  /**
   * Turn the tree into hierarchy before running map and filter tests.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->collection->buildTree();
  }

  /**
   * Test that a branch filter on a tree removes all children of branch.
   */
  public function testBranchFilter(): void {
    $this->collection->filter(fn(Record $record) => $record->getId() !== 5);
    $this->assertCount(6, $this->collection);
    $ids = $this->collection->map(fn (Record $record) => $record->getId());
    $this->assertEqualsCanonicalizing([1, 2, 3, 4, 7, 8], $ids);
  }

}
