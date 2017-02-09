<?php

namespace Drupal\Tests\entity_hierarchy\Kernel;

use Console_Table;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityInterface;
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
   * Node key factory.
   *
   * @var \Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory
   */
  protected $nodeFactory;

  /**
   * Node key for parent.
   *
   * @var \PNX\NestedSet\NodeKey
   */
  protected $parentStub;

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
    $this->nodeFactory = $this->container->get('entity_hierarchy.nested_set_node_factory');
    $this->parentStub = $this->nodeFactory->fromEntity($this->parent);
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
    $this->assertSimpleParentChild($child);
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
    $root_node = $this->treeStorage->getNode($this->parentStub);
    $children = $this->treeStorage->findChildren($root_node->getNodeKey());
    $this->assertCount(5, $children);
    $this->assertEquals(array_map(function ($name) use ($entities) {
      return $entities[$name]->id();
    }, ['Child 5', 'Child 4', 'Child 3', 'Child 2', 'Child 1']), array_map(function (Node $node) {
      return $node->getId();
    }, $children));
    // Now insert one in the middle.
    $name = 'Child 6';
    $entities[$name] = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => $name,
      self::FIELD_NAME => [
        'target_id' => $this->parent->id(),
        // We insert them in reverse order.
        'weight' => -2,
      ],
    ]);
    $entities[$name]->save();
    $children = $this->treeStorage->findChildren($root_node->getNodeKey());
    $this->assertCount(6, $children);
    $this->assertEquals(array_map(function ($name) use ($entities) {
      return $entities[$name]->id();
    }, ['Child 5', 'Child 4', 'Child 3', 'Child 6', 'Child 2', 'Child 1']), array_map(function (Node $node) {
      return $node->getId();
    }, $children));
  }

  /**
   * Tests removing parent reference.
   */
  public function testRemoveParentReference() {
    $child = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => 'Child 1',
      self::FIELD_NAME => [
        'target_id' => $this->parent->id(),
        'weight' => 0,
      ],
    ]);
    $child->save();
    $root_node = $this->treeStorage->getNode($this->parentStub);
    $children = $this->treeStorage->findChildren($root_node->getNodeKey());
    $this->assertCount(1, $children);
    $child->set(self::FIELD_NAME, NULL);
    $child->save();
    $children = $this->treeStorage->findChildren($root_node->getNodeKey());
    $this->assertCount(0, $children);
    $child_node = $this->treeStorage->getNode($this->nodeFactory->fromEntity($child));
    $this->assertEquals(0, $child_node->getDepth());
  }

  /**
   * Tests deleting child node.
   */
  public function testDeleteChild() {
    $child = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => 'Child 1',
      self::FIELD_NAME => [
        'target_id' => $this->parent->id(),
        'weight' => 0,
      ],
    ]);
    $child->save();
    $root_node = $this->treeStorage->getNode($this->parentStub);
    $children = $this->treeStorage->findChildren($root_node->getNodeKey());
    $this->assertCount(1, $children);
    $child->delete();
    $children = $this->treeStorage->findChildren($root_node->getNodeKey());
    $this->assertCount(0, $children);
  }

  /**
   * Tests deleting child node with grandchildren.
   */
  public function testDeleteChildWithGrandChildren() {
    $child = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => 'Child 1',
      self::FIELD_NAME => [
        'target_id' => $this->parent->id(),
        'weight' => 0,
      ],
    ]);
    $child->save();
    $grand_child = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => 'Grandchild 1',
      self::FIELD_NAME => [
        'target_id' => $child->id(),
        'weight' => 0,
      ],
    ]);
    $grand_child->save();
    $root_node = $this->treeStorage->getNode($this->parentStub);
    $children = $this->treeStorage->findChildren($root_node->getNodeKey());
    $this->assertCount(1, $children);
    $childNode = reset($children);
    $grand_children = $this->treeStorage->findChildren($childNode->getNodeKey());
    $this->assertCount(1, $grand_children);
    $grandChildNode = reset($grand_children);
    $this->assertEquals($grand_child->id(), $grandChildNode->getId());
    $child->delete();
    $children = $this->treeStorage->findChildren($root_node->getNodeKey());
    $this->assertCount(1, $children);
    $this->assertEquals(reset($grand_children)->getId(), reset($children)->getId());
  }

  /**
   * Tests removing parent reference with grandchildren.
   */
  public function testRemoveParentReferenceWithGrandChildren() {
    $child = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => 'Child 1',
      self::FIELD_NAME => [
        'target_id' => $this->parent->id(),
        'weight' => 0,
      ],
    ]);
    $child->save();
    $grand_child = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => 'Grandchild 1',
      self::FIELD_NAME => [
        'target_id' => $child->id(),
        'weight' => 0,
      ],
    ]);
    $grand_child->save();
    $root_node = $this->treeStorage->getNode($this->parentStub);
    $children = $this->treeStorage->findChildren($root_node->getNodeKey());
    $this->assertCount(1, $children);
    $child->set(self::FIELD_NAME, NULL);
    $childNode = reset($children);
    $grand_children = $this->treeStorage->findChildren($childNode->getNodeKey());
    $this->assertCount(1, $grand_children);
    $grandChildNode = reset($grand_children);
    $this->assertEquals($grand_child->id(), $grandChildNode->getId());
    $this->assertEquals(2, $grandChildNode->getDepth());
    $child->set(self::FIELD_NAME, NULL);
    $child->save();
    $children = $this->treeStorage->findChildren($root_node->getNodeKey());
    $this->assertCount(0, $children);
    $child_node = $this->treeStorage->getNode($this->nodeFactory->fromEntity($child));
    $this->assertEquals(0, $child_node->getDepth());
    $grand_children = $this->treeStorage->findChildren($child_node->getNodeKey());
    $this->assertCount(1, $grand_children);
    $grandChildNode = reset($grand_children);
    $this->assertEquals($grand_child->id(), $grandChildNode->getId());
    $this->assertEquals(1, $grandChildNode->getDepth());
  }

  /**
   * Tests saving with existing parent (no value change).
   */
  public function testNestedSetStorageSimpleUpdate() {
    $child = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => 'Child 1',
      self::FIELD_NAME => [
        'target_id' => $this->parent->id(),
        'weight' => 0,
      ],
    ]);
    $child->save();
    $this->assertSimpleParentChild($child);
    $child->save();
    $this->assertSimpleParentChild($child);
  }

  /**
   * Tests saving with existing parent and sibling (no value change).
   */
  public function testNestedSetStorageWithSiblingUpdate() {
    $child = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => 'Child 1',
      self::FIELD_NAME => [
        'target_id' => $this->parent->id(),
        'weight' => 1,
      ],
    ]);
    $child->save();
    $sibling = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => 'Child 2',
      self::FIELD_NAME => [
        'target_id' => $this->parent->id(),
        'weight' => 2,
      ],
    ]);
    $sibling->save();
    $this->assertParentWithTwoChildren($child, $sibling);
    $child->save();
    $this->assertParentWithTwoChildren($child, $sibling);
  }

  // Test for new revisions.

  /**
   * Tests moving parents.
   */
  public function testNestedSetStorageMoveParent() {
    $child = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => 'Child 1',
      self::FIELD_NAME => [
        'target_id' => $this->parent->id(),
        'weight' => 1,
      ],
    ]);
    $child->save();
    $parent2 = EntityTest::create([
      'type' => self::ENTITY_TYPE,
      'name' => 'Parent 2',
    ]);
    $parent2->save();
    $this->assertSimpleParentChild($child);
    $child->set(self::FIELD_NAME, $parent2->id());
    $child->save();
    $this->assertSimpleParentChild($child, $parent2);
  }

  // Test for moving a tree
  // Test for new parent with weight ordering
  // Test for going from non child to child.
  // Test for going from non child, to child of parent with existing children.

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

  /**
   * Prints out a tree to the console.
   *
   * @param array $tree
   *   The tree to print.
   */
  public function printTree($tree) {
    $table = new Console_Table(CONSOLE_TABLE_ALIGN_RIGHT);
    $table->setHeaders(['ID', 'Rev', 'Left', 'Right', 'Depth']);
    $table->setAlign(0, CONSOLE_TABLE_ALIGN_LEFT);
    /** @var Node $node */
    foreach ($tree as $node) {
      $indent = str_repeat('-', $node->getDepth());
      $table->addRow([
        $indent . $node->getId(),
        $node->getRevisionId(),
        $node->getLeft(),
        $node->getRight(),
        $node->getDepth(),
      ]);
    }
    echo PHP_EOL . $table->getTable();
  }

  /**
   * Test parent/child relationship.
   *
   * @param \Drupal\Core\Entity\EntityInterface $child
   *   Child node.
   * @param \Drupal\Core\Entity\EntityInterface $parent
   *   (optional) Parent to test relationship with, defaults to the one
   *   created in setup if not passed.
   */
  protected function assertSimpleParentChild(EntityInterface $child, EntityInterface $parent = NULL) {
    $parent = $parent ?: $this->parent;
    $root_node = $this->treeStorage->getNode($this->nodeFactory->fromEntity($parent));
    $this->assertNotEmpty($root_node);
    $this->assertEquals($parent->id(), $root_node->getId());
    $this->assertEquals($parent->id(), $root_node->getRevisionId());
    $this->assertEquals(0, $root_node->getDepth());
    $children = $this->treeStorage->findChildren($root_node->getNodeKey());
    $this->assertCount(1, $children);
    $first = reset($children);
    $this->assertEquals($child->id(), $first->getId());
    $this->assertEquals($child->id(), $first->getRevisionId());
    $this->assertEquals(1, $first->getDepth());
  }

  /**
   * Test parent/child relationship.
   *
   * @param \Drupal\Core\Entity\EntityInterface $child
   *   Child node.
   * @param \Drupal\Core\Entity\EntityInterface $sibling
   *   Sibling node.
   */
  protected function assertParentWithTwoChildren(EntityInterface $child, EntityInterface $sibling) {
    $root_node = $this->treeStorage->getNode($this->parentStub);
    $this->assertNotEmpty($root_node);
    $this->assertEquals($this->parent->id(), $root_node->getId());
    $this->assertEquals($this->parent->id(), $root_node->getRevisionId());
    $this->assertEquals(0, $root_node->getDepth());
    $children = $this->treeStorage->findChildren($root_node->getNodeKey());
    $this->assertCount(2, $children);
    $first = reset($children);
    $this->assertEquals($child->id(), $first->getId());
    $this->assertEquals($child->id(), $first->getRevisionId());
    $this->assertEquals(1, $first->getDepth());
    $last = end($children);
    $this->assertEquals($sibling->id(), $last->getId());
    $this->assertEquals($sibling->id(), $last->getRevisionId());
    $this->assertEquals(1, $last->getDepth());
  }

}
