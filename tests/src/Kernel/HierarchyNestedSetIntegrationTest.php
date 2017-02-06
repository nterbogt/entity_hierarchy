<?php

namespace Drupal\Tests\entity_hierarchy\Kernel;

use Drupal\Component\Utility\Unicode;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PNX\NestedSet\Node;

/**
 * Tests integration with entity_hierarchy.
 *
 * @group entity_hierarchy
 */
class HierarchyNestedSetIntegrationTest extends KernelTestBase {

  const FIELD_NAME = 'parents';
  const ENTITY_TYPE = 'entity_test';

  /**
   * Test parent.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $parent;

  /**
   * Tree storage.
   *
   * @var \PNX\NestedSet\Storage\DbalNestedSet
   */
  protected $treeStorage;

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
    $this->setupEntityHierarchyField(self::ENTITY_TYPE, self::ENTITY_TYPE, self::FIELD_NAME);

    $this->treeStorage = $this->container->get('entity_hierarchy.nested_set_storage_factory')->get(self::FIELD_NAME, self::ENTITY_TYPE);

    $this->parent = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => 'Parent',
    ]);
    $this->parent->save();
  }

  /**
   * Tests simple storage in nested set tables.
   */
  public function testNestedSetStorageSimple() {
    $child = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => 'Child 1',
      self::FIELD_NAME => [
        'target_id' => $this->parent->id(),
        'weight' => 0,
      ],
    ]);
    $child->save();
    $root_node = $this->treeStorage->getNode($this->parent->id(), $this->parent->id());
    $this->assertNotEmpty($root_node);
    $this->assertEquals($this->parent->id(), $root_node->getId());
    $this->assertEquals($this->parent->id(), $root_node->getRevisionId());
    $this->assertEquals(0, $root_node->getDepth());
    $children = $this->treeStorage->findChildren($root_node);
    $this->assertCount(1, $children);
    $first = reset($children);
    $this->assertEquals($child->id(), $first->getId());
    $this->assertEquals($child->id(), $first->getRevisionId());
    $this->assertEquals(1, $first->getDepth());
  }

  /**
   * Tests ordered storage in nested set tables.
   */
  public function testNestedSetOrdering() {
    // Test for weight ordering of inserts.
    $entities  = [];
    foreach (range(1, 5) as $i) {
      $name = sprintf('Child %d', $i);
      $entities[$name] = EntityTest::create([
        'type' => self::ENTITY_TYPE,
        'name' => $name,
        self::FIELD_NAME => [
          'target_id' => $this->parent->id(),
          // We insert them in reverse order.
          'weight' => -1 * $i,
        ],
      ]);
      $entities[$name]->save();
    }
    $root_node = $this->treeStorage->getNode($this->parent->id(), $this->parent->id());
    $children = $this->treeStorage->findChildren($root_node);
    $this->assertCount(5, $children);
    $this->assertEquals(array_map(function ($name) use ($entities) {
      return $entities[$name]->id();
    }, ['Child 5', 'Child 4', 'Child 3', 'Child 2', 'Child 1']), array_map(function (Node $node) {
      return $node->getId();
    }, $children));
  }

  // Test for deleting.
  // Test for saving with existing parent (no value change).
  // Test for new revisions.
  // Test for new parent.
  // Test for moving a tree.

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
