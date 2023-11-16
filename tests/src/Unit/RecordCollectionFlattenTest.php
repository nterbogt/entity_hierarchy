<?php

namespace Drupal\Tests\entity_hierarchy\Unit;

/**
 * Runs record collection tests against an altered tree.
 *
 * @group entity_hierarchy
 *
 * @coversDefaultClass \Drupal\entity_hierarchy\Storage\RecordCollection
 */
class RecordCollectionFlattenTest extends RecordCollectionTest {

  /**
   * Build a tree and flatten it again before parent class tests are run.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->collection
      ->buildTree()
      ->flatten();
  }

}
