<?php

namespace Drupal\Tests\entity_hierarchy\Kernel;

use Drupal\Component\Utility\Unicode;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
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
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema(self::ENTITY_TYPE);
  }

  /**
   * Tests storage in nested set tables.
   */
  public function testNestedSetStorage() {
    /** @var \PNX\NestedSet\Storage\DbalNestedSet $tree_storage */
    $tree_storage = $this->container->get('entity_hierarchy.nested_set_storage_factory')->get(self::FIELD_NAME, self::ENTITY_TYPE);
    $this->setupEntityHierarchyField(self::ENTITY_TYPE, self::ENTITY_TYPE, self::FIELD_NAME);
    $parent = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => 'Parent',
    ]);
    $parent->save();

    $child = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => 'Child 1',
      self::FIELD_NAME => [
        'target_id' => $parent->id(),
        'weight' => 0,
      ],
    ]);
    $child->save();
    $root_node = $tree_storage->getNode($parent->id(), $parent->id());
    $this->assertNotEmpty($root_node);
    $this->assertEquals($parent->id(), $root_node->getId());
    $this->assertEquals($parent->id(), $root_node->getRevisionId());
    $this->assertEquals(0, $root_node->getDepth());
    $children = $tree_storage->findChildren($root_node);
    $this->assertCount(1, $children);
    $first = reset($children);
    $this->assertEquals($child->id(), $first->getId());
    $this->assertEquals($child->id(), $first->getRevisionId());
    $this->assertEquals(1, $first->getDepth());
    // Test for weight ordering of inserts.
    // Test for deleting.
    // Test for new revisions.
  }

  /**
   * Creates a new entity hierarchy field for the given bundle.
   *
   * @param string $entity_type_id
   *   Entity type to add the field to.
   * @param string $bundle
   *   Bundle of field.
   * @param string $field_name
   *   Field name.
   */
  protected function setupEntityHierarchyField($entity_type_id, $bundle, $field_name) {
    $storage = FieldStorageConfig::create([
      'entity_type' => $entity_type_id,
      'field_name' => $field_name,
      'id' => "$entity_type_id.$field_name",
      'type' => 'entity_reference_hierarchy',
      'settings' => [
        'target_type' => $entity_type_id,
      ],
    ]);
    $storage->save();
    $config = FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type_id,
      'bundle' =>  $bundle,
      'id' => "$entity_type_id.$bundle.$field_name",
      'label' => Unicode::ucfirst($field_name),
    ]);
    $config->save();
  }

}
