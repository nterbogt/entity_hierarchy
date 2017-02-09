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
   * Perform additional setup.
   */
  protected function additionalSetup() {
    $this->treeStorage = $this->container->get('entity_hierarchy.nested_set_storage_factory')->get(static::FIELD_NAME, static::ENTITY_TYPE);

    $this->parent = $this->createTestEntity(NULL, 'Parent');
    $this->nodeFactory = $this->container->get('entity_hierarchy.nested_set_node_factory');
    $this->parentStub = $this->nodeFactory->fromEntity($this->parent);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema(static::ENTITY_TYPE);
    $this->setupEntityHierarchyField(static::ENTITY_TYPE, static::ENTITY_TYPE, static::FIELD_NAME);
    $this->additionalSetup();
  }

  /**
   * Tests simple storage in nested set tables.
   */
  public function testNestedSetStorageSimple() {
    $child = $this->createTestEntity($this->parent->id());
    $this->assertSimpleParentChild($child);
  }

  /**
   * Tests ordered storage in nested set tables.
   */
  public function testNestedSetOrdering() {
    // Test for weight ordering of inserts.
    $entities = $this->createChildEntities($this->parent->id());
    $root_node = $this->treeStorage->getNode($this->parentStub);
    $children = $this->getChildren($root_node);
    $this->assertCount(5, $children);
    $this->assertEquals(array_map(function ($name) use ($entities) {
      return $entities[$name]->id();
    }, ['Child 5', 'Child 4', 'Child 3', 'Child 2', 'Child 1']), array_map(function (Node $node) {
      return $node->getId();
    }, $children));
    // Now insert one in the middle.
    $name = 'Child 6';
    $entities[$name] = $this->createTestEntity($this->parent->id(), $name, -2);
    $this->printTree($this->treeStorage->getTree());
    $children = $this->getChildren($root_node);
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
    $child = $this->createTestEntity($this->parent->id());
    $root_node = $this->treeStorage->getNode($this->parentStub);
    $this->assertSimpleParentChild($child);
    $child->set(static::FIELD_NAME, NULL);
    $child->save();
    $children = $this->getChildren($root_node);
    $this->assertCount(0, $children);
    $child_node = $this->treeStorage->getNode($this->nodeFactory->fromEntity($child));
    $this->assertEquals(0, $child_node->getDepth());
  }

  /**
   * Tests deleting child node.
   */
  public function testDeleteChild() {
    $child = $this->createTestEntity($this->parent->id());
    $root_node = $this->treeStorage->getNode($this->parentStub);
    $children = $this->getChildren($root_node);
    $this->assertCount(1, $children);
    $child->delete();
    $children = $this->getChildren($root_node);
    $this->assertCount(0, $children);
  }

  /**
   * Tests deleting child node with grandchildren.
   */
  public function testDeleteChildWithGrandChildren() {
    $child = $this->createTestEntity($this->parent->id());
    $grand_child = $this->createTestEntity($child->id(), 'Grandchild 1', 1);
    $this->assertSimpleParentChild($child);
    $this->assertSimpleParentChild($grand_child, $child, 1);
    $child->delete();
    $this->assertSimpleParentChild($grand_child, $this->parent);
  }

  /**
   * Tests removing parent reference with grandchildren.
   */
  public function testRemoveParentReferenceWithGrandChildren() {
    $child = $this->createTestEntity($this->parent->id());
    $grand_child = $this->createTestEntity($child->id(), 'Grandchild 1', 1);
    $root_node = $this->treeStorage->getNode($this->parentStub);
    $this->assertSimpleParentChild($child);
    $this->assertSimpleParentChild($grand_child, $child, 1);
    $child->set(static::FIELD_NAME, NULL);
    $child->save();
    $children = $this->getChildren($root_node);
    $this->assertCount(0, $children);
    // Should now be at top level.
    $this->assertSimpleParentChild($grand_child, $child);
  }

  /**
   * Tests saving with existing parent (no value change).
   */
  public function testNestedSetStorageSimpleUpdate() {
    $child = $this->createTestEntity($this->parent->id());
    $this->assertSimpleParentChild($child);
    $child->save();
    $this->assertSimpleParentChild($child);
  }

  /**
   * Tests saving with existing parent and sibling (no value change).
   */
  public function testNestedSetStorageWithSiblingUpdate() {
    $child = $this->createTestEntity($this->parent->id(), 'Child 1', 1);
    $sibling = $this->createTestEntity($this->parent->id(), 'Child 2', 2);
    $this->assertParentWithTwoChildren($child, $sibling);
    $child->save();
    $this->assertParentWithTwoChildren($child, $sibling);
  }

  /**
   * Tests moving parents.
   */
  public function testNestedSetStorageMoveParent() {
    $child = $this->createTestEntity($this->parent->id(), 'Child 1', 1);
    $parent2 = $this->createTestEntity(NULL, 'Parent 2');
    $parent2->save();
    $this->assertSimpleParentChild($child);
    $child->set(static::FIELD_NAME, $parent2->id());
    $child->save();
    $this->assertSimpleParentChild($child, $parent2);
  }

  // Test for moving a tree
  /**
   * Tests moving tree.
   */
  public function testNestedSetStorageMoveParentWithChildren() {
    $child = $this->createTestEntity($this->parent->id(), 'Child 1', 1);
    $parent2 = $this->createTestEntity(NULL, 'Parent 2');
    $grandchild = $this->createTestEntity($child->id(), 'Grandchild 1', 1);
    $this->assertSimpleParentChild($child);
    $this->assertSimpleParentChild($grandchild, $child, 1);
    $child->set(static::FIELD_NAME, $parent2->id());
    $child->save();
    $this->assertSimpleParentChild($child, $parent2);
    $this->assertSimpleParentChild($grandchild, $child, 1);
  }

  /**
   * Tests moving parents with weight ordering.
   */
  public function testNestedSetStorageMoveParentWithSiblingOrdering() {
    $child = $this->createTestEntity($this->parent->id(), 'Cousin 1', -2);
    $parent2 = $this->createTestEntity(NULL, 'Parent 2');
    $child_entities = $this->createChildEntities($parent2->id(), 5);
    $child_entities['Cousin 1'] = $child;
    $this->assertSimpleParentChild($child);
    $child->set(static::FIELD_NAME, $parent2->id());
    $child->save();
    $children = $this->treeStorage->findChildren($this->nodeFactory->fromEntity($parent2));
    $this->assertCount(6, $children);
    $this->assertEquals(array_map(function ($name) use ($child_entities) {
      return $child_entities[$name]->id();
    }, ['Child 5', 'Child 4', 'Child 3', 'Cousin 1', 'Child 2', 'Child 1']), array_map(function (Node $node) {
      return $node->getId();
    }, $children));
  }

  /**
   * Tests moving from out of tree, into tree.
   */
  public function testNestedSetParentToChild() {
    $child = $this->createTestEntity(NULL);
    $child->set(static::FIELD_NAME, $this->parent->id());
    $child->save();
    $this->assertSimpleParentChild($child);
  }

  /**
   * Tests moving from out of tree, into tree with existing siblings.
   */
  public function testNestedSetParentToChildWithSiblings() {
    $child = $this->createTestEntity(NULL, 'Once was a parent');
    $entities = $this->createChildEntities($this->parent->id());
    $entities[$child->label()] = $child;
    $child->{static::FIELD_NAME} = [
      'target_id' => $this->parent->id(),
      'weight' => -2,
    ];
    $child->save();
    $children = $this->treeStorage->findChildren($this->parentStub);
    $this->assertEquals(array_map(function ($name) use ($entities) {
      return $entities[$name]->id();
    }, ['Child 5', 'Child 4', 'Child 3', 'Once was a parent', 'Child 2', 'Child 1']), array_map(function (Node $node) {
      return $node->getId();
    }, $children));
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
   * @param int $baseDepth
   *   (optional) Base depth to add, defaults to 0.
   */
  protected function assertSimpleParentChild(EntityInterface $child, EntityInterface $parent = NULL, $baseDepth = 0) {
    $parent = $parent ?: $this->parent;
    $root_node = $this->treeStorage->getNode($this->nodeFactory->fromEntity($parent));
    $this->assertNotEmpty($root_node);
    $this->assertEquals($parent->id(), $root_node->getId());
    $this->assertEquals($this->getEntityRevisionId($parent), $root_node->getRevisionId());
    $this->assertEquals(0 + $baseDepth, $root_node->getDepth());
    $children = $this->getChildren($root_node);
    $this->assertCount(1, $children);
    $first = reset($children);
    $this->assertEquals($child->id(), $first->getId());
    $this->assertEquals($child->id(), $first->getRevisionId());
    $this->assertEquals(1 + $baseDepth, $first->getDepth());
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
    $children = $this->getChildren($root_node);
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

  /**
   * Create child entities.
   *
   * @param int $parentId
   *   Parent ID.
   * @param int $count
   *   (optional) Number to create. Defaults to 5
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Child entities
   */
  protected function createChildEntities($parentId, $count = 5) {
    $entities = [];
    foreach (range(1, $count) as $i) {
      $label = sprintf('Child %d', $i);
      $entities[$label] = $this->doCreateChildTestEntity($parentId, $label, $i);
    }
    return $entities;
  }

  /**
   * Creates a new test entity.
   *
   * @param int|null $parentId
   *   Parent ID
   * @param string $label
   *   Entity label
   * @param int $weight
   *   Entity weight amongst sibling, if parent is set.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   New entity.
   */
  protected function createTestEntity($parentId, $label = 'Child 1', $weight = 0) {
    $values = [
      'type' => static::ENTITY_TYPE,
      'name' => $label,
    ];
    if ($parentId) {
      $values[static::FIELD_NAME] = [
        'target_id' => $parentId,
        'weight' => $weight,
      ];
    }
    $entity = $this->doCreateTestEntity($values);
    $entity->save();
    return $entity;
  }

  /**
   * Creates the test entity.
   *
   * @param array $values
   *   Entity values.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Created entity.
   */
  protected function doCreateTestEntity($values) {
    $entity = EntityTest::create($values);
    return $entity;
  }

  /**
   * Gets the revision ID for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity revision ID if it exists, otherwise entity ID.
   *
   * @return int
   *   Revision ID.
   */
  protected function getEntityRevisionId(EntityInterface $entity) {
    $id = $entity->id();
    if (!$revision_id = $entity->getRevisionId()) {
      $revision_id = $id;
    }
    return $revision_id;
  }

  /**
   * Creates a new test entity.
   *
   * @param int|null $parentId
   *   Parent ID
   * @param string $label
   *   Entity label
   * @param int $weight
   *   Entity weight amongst sibling, if parent is set.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   New entity.
   */
  protected function doCreateChildTestEntity($parentId, $label, $weight) {
    return $this->createTestEntity($parentId, $label, -1 * $weight);
  }

  /**
   * Gets children of a given node.
   *
   * @param \PNX\NestedSet\Node $parent_node
   *   Parent node.
   *
   * @return \PNX\NestedSet\Node[]
   *   Children
   */
  protected function getChildren($parent_node) {
    return $this->treeStorage->findChildren($parent_node->getNodeKey());
  }

}
