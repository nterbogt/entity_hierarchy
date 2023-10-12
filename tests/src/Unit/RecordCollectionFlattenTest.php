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
class RecordCollectionFlattenTest extends RecordCollectionTest {

  protected function setUp(): void {
    parent::setUp();

    $this->collection
      ->buildTree()
      ->flatten();
  }

}
