<?php

namespace Drupal\Tests\entity_hierarchy\Unit;

/**
 * @group entity_hierarchy
 *
 * @coversDefaultClass \Drupal\entity_hierarchy\Storage\RecordCollection
 */
class RecordCollectionFlattenTest extends RecordCollectionTest {

  protected function setUp(): void {
    parent::setUp();

    $this->collection
      ->buildTree()
      ->flatten();
  }

}
