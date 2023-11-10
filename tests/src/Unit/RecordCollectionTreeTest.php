<?php

namespace Drupal\Tests\entity_hierarchy\Unit;

use Drupal\entity_hierarchy\Storage\Record;

/**
 * @group entity_hierarchy
 *
 * @coversDefaultClass \Drupal\entity_hierarchy\Storage\RecordCollection
 */
class RecordCollectionTreeTest extends RecordCollectionTest {

  protected function setUp(): void {
    parent::setUp();

    $this->collection->buildTree();
  }

  public function testBranchFilter() {
    $this->collection->filter(fn(Record $record) => $record->getId() !== 5);
    $this->assertCount(6, $this->collection);
    $ids = $this->collection->map(fn (Record $record) => $record->getId());
    $this->assertEqualsCanonicalizing([1, 2, 3, 4, 7, 8], $ids);
  }

}
