<?php

namespace Drupal\Tests\entity_hierarchy\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests integration with entity_hierarchy.
 *
 * @group entity_hierarchy
 */
class HierarchyNestedSetIntegrationTest extends KernelTestBase {

  const FIELD_NAME = 'parents';
  const ENTITY_TYPE = 'entity_test';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_hierarchy',
    'entity_test',
    'system',
    'user',
    'dbal',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
  }

  public function testNestedSetStorage() {
    $tree_storage = $this->container->get('entity_hierarchy.nested_set_storage_factory')->get(self::FIELD_NAME, self::ENTITY_TYPE);
  }

}
