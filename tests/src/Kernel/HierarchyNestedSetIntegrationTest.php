<?php

namespace Drupal\Tests\entity_hierarchy\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_hierarchy\Storage\Record;
use Drupal\entity_hierarchy\Storage\RecordCollection;

/**
 * Tests integration with entity_hierarchy.
 *
 * @group entity_hierarchy
 */
class HierarchyNestedSetIntegrationTest extends EntityHierarchyKernelTestBase {

  /**
   * Tests simple storage in nested set tables.
   */
  public function testHierarchySimple() {
    $child = $this->createTestEntity($this->parent->id());
    $this->assertSimpleParentChild($child);
  }

  /**
   * Tests ordered storage in nested set tables.
   *
   * @group entity_hierarchy_ordering
   */
  public function testChildOrdering(): void {
    // Test for weight ordering of inserts.
    $entities = $this->createChildEntities($this->parent->id());
    $this->assertChildOrder($this->parent, $entities, [
      'Child 5',
      'Child 4',
      'Child 3',
      'Child 2',
      'Child 1',
    ]);
    // Now insert one in the middle.
    $name = 'Child 6';
    $entities[$name] = $this->createTestEntity($this->parent->id(), $name, -2);
    $this->assertChildOrder($this->parent, $entities, [
      'Child 5',
      'Child 4',
      'Child 3',
      'Child 2',
      'Child 6',
      'Child 1',
    ]);
  }

  /**
   * Tests removing parent reference.
   */
  public function testRemoveParentReference(): void {
    $child = $this->createTestEntity($this->parent->id());
    $this->assertSimpleParentChild($child);
    $child->set(static::FIELD_NAME, NULL);
    $child->save();
    $children = $this->getChildren($this->parent);
    $this->assertCount(0, $children);
    $this->assertEquals(0, $this->queryBuilder->findDepth($child));
  }

  /**
   * Tests deleting child node.
   */
  public function testDeleteChild(): void {
    $child = $this->createTestEntity($this->parent->id());
    $children = $this->getChildren($this->parent);
    $this->assertCount(1, $children);
    $child->delete();
    $children = $this->getChildren($this->parent);
    $this->assertCount(0, $children);
  }

  /**
   * Tests deleting parent node reparents children.
   */
  public function testDeleteParent(): void {
    $child = $this->createTestEntity($this->parent->id());
    $child2 = $this->createTestEntity($this->parent->id());
    $this->createTestEntity($child->id());
    $grandchild2 = $this->createTestEntity($child2->id());
    $this->assertEquals(2, $this->queryBuilder->findDepth($grandchild2));
    $children = $this->getChildren($this->parent);
    $this->assertCount(2, $children);
    // Now we delete child2, grandchild2 should go up a layer.
    $child2->delete();
    $children = $this->getChildren($this->parent);
    $this->assertCount(2, $children);
    $reload = function ($id) {
      return \Drupal::entityTypeManager()->getStorage(static::ENTITY_TYPE)->loadUnchanged($id);
    };
    $grandchild2 = $reload($grandchild2->id());
    $field_name = static::FIELD_NAME;
    $this->assertNotNull($grandchild2);
    $this->assertEquals($this->parent->id(), $grandchild2->{$field_name}->target_id);
    $this->assertEquals(1, $this->queryBuilder->findDepth($grandchild2));
    // Confirm field values were updated.
    $this->parent->delete();
    // Grandchild2 and child should now be parentless.
    $grandchild2 = $reload($grandchild2->id());
    $this->assertEquals(0, $this->queryBuilder->findDepth($grandchild2));
    $grandchild2 = $reload($grandchild2->id());
    $child = $reload($grandchild2->id());
    // Confirm field values were updated.
    $this->assertEquals(NULL, $grandchild2->{self::FIELD_NAME}->target_id);
    $this->assertEquals(NULL, $child->{self::FIELD_NAME}->target_id);
  }

  /**
   * Tests deleting child node with grandchildren.
   */
  public function testDeleteChildWithGrandChildren(): void {
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
  public function testRemoveParentReferenceWithGrandChildren(): void {
    $child = $this->createTestEntity($this->parent->id());
    $grand_child = $this->createTestEntity($child->id(), 'Grandchild 1', 1);
    $this->assertSimpleParentChild($child);
    $this->assertSimpleParentChild($grand_child, $child, 1);
    $child->set(static::FIELD_NAME, NULL);
    $child->save();
    $children = $this->getChildren($this->parent);
    $this->assertCount(0, $children);
    // Should now be at top level.
    $this->assertSimpleParentChild($grand_child, $child);
  }

  /**
   * Tests saving with existing parent (no value change).
   */
  public function testContentUpdate(): void {
    $child = $this->createTestEntity($this->parent->id());
    $this->assertSimpleParentChild($child);
    $child->save();
    $this->assertSimpleParentChild($child);
  }

  /**
   * Tests saving with existing parent and sibling (no value change).
   */
  public function testContentWithSiblingUpdate(): void {
    $child = $this->createTestEntity($this->parent->id(), 'Child 1', 1);
    $sibling = $this->createTestEntity($this->parent->id(), 'Child 2', 2);
    $this->assertParentWithTwoChildren($child, $sibling);
    $child->save();
    $this->assertParentWithTwoChildren($child, $sibling);
  }

  /**
   * Tests moving parents.
   */
  public function testMoveParent(): void {
    $child = $this->createTestEntity($this->parent->id(), 'Child 1', 1);
    $parent2 = $this->createTestEntity(NULL, 'Parent 2');
    $parent2->save();
    $this->assertSimpleParentChild($child);
    $child->set(static::FIELD_NAME, $parent2->id());
    $child->save();
    $this->assertSimpleParentChild($child, $parent2);
  }

  /**
   * Tests moving tree.
   */
  public function testMoveParentWithChildren(): void {
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
   *
   * @group entity_hierarchy_ordering
   */
  public function testMoveParentWithSiblingOrdering(): void {
    $child = $this->createTestEntity($this->parent->id(), 'Cousin 1', -2);
    $parent2 = $this->createTestEntity(NULL, 'Parent 2');
    $child_entities = $this->createChildEntities($parent2->id());
    $child_entities['Cousin 1'] = $child;
    $this->assertSimpleParentChild($child);
    $child->set(static::FIELD_NAME, $parent2->id());
    $child->save();
    $this->assertChildOrder($parent2, $child_entities, [
      'Child 5',
      'Child 4',
      'Child 3',
      'Cousin 1',
      'Child 2',
      'Child 1',
    ]);
  }

  /**
   * Tests moving from out of tree, into tree.
   */
  public function testParentToChild(): void {
    $child = $this->createTestEntity(NULL);
    $child->set(static::FIELD_NAME, $this->parent->id());
    $child->save();
    $this->assertSimpleParentChild($child);
  }

  /**
   * Tests moving from out of tree, into tree with existing siblings.
   *
   * @group entity_hierarchy_ordering
   */
  public function testParentToChildWithSiblings(): void {
    $child = $this->createTestEntity(NULL, 'Once was a parent');
    $entities = $this->createChildEntities($this->parent->id());
    $entities[$child->label()] = $child;
    $child->{static::FIELD_NAME} = [
      'target_id' => $this->parent->id(),
      'weight' => -2,
    ];
    $child->save();
    $this->assertChildOrder($this->parent, $entities, [
      'Child 5',
      'Child 4',
      'Child 3',
      'Once was a parent',
      'Child 2',
      'Child 1',
    ]);
  }

  /**
   * Test saving the parent after adding children.
   */
  public function testResaveParent(): void {
    // Test for weight ordering of inserts.
    $entities = $this->createChildEntities($this->parent->id());
    $this->assertChildOrder($this->parent, $entities, [
      'Child 5',
      'Child 4',
      'Child 3',
      'Child 2',
      'Child 1',
    ]);
    // Now insert one in the middle.
    $name = 'Child 6';
    $entities[$name] = $this->createTestEntity($this->parent->id(), $name, -2);
    $this->assertChildOrder($this->parent, $entities, [
      'Child 5',
      'Child 4',
      'Child 3',
      'Child 2',
      'Child 6',
      'Child 1',
    ]);
    $this->resaveParent();
    $this->assertChildOrder($this->parent, $entities, [
      'Child 5',
      'Child 4',
      'Child 3',
      'Child 2',
      'Child 6',
      'Child 1',
    ]);
  }

  /**
   * Test that we're getting descendants correctly.
   */
  public function testGrandchildrenSimple(): void {
    $entities = $this->createChildEntities($this->parent->id(), 2);
    $first_child = reset($entities);
    $second_child = end($entities);
    $this->createChildEntities($first_child->id(), 3);
    $this->createTestEntity($second_child->id());
    $descendants = $this->getDescendants($this->parent);
    $this->assertCount(6, $descendants);
    $descendants = $this->getDescendants($this->parent, 1);
    $this->assertCount(2, $descendants);
    $descendants = $this->getDescendants($this->parent, 2, 2);
    $this->assertCount(4, $descendants);
  }

  /**
   * Re-saves the parent, with option to include new revision.
   */
  protected function resaveParent() {
    $this->parent->save();
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
    $this->assertEquals(0 + $baseDepth, $this->queryBuilder->findDepth($parent));
    $children = $this->getChildren($parent);
    $this->assertCount(1, $children);
    $first = $children->getIterator()->current();
    $this->assertEquals($child->id(), $first->getId());
    $this->assertEquals($this->getEntityRevisionId($child), $first->getRevisionId());
    $this->assertEquals(1 + $baseDepth, $this->queryBuilder->findDepth($child));
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
    // Just turn all the records into a flat array to make the test easier.
    $children = $this->getChildren($this->parent)->map(fn (Record $record) => $record);
    $this->assertCount(2, $children);
    $first = reset($children);
    $this->assertEquals($child->id(), $first->getId());
    $this->assertEquals($this->getEntityRevisionId($child), $first->getRevisionId());
    $this->assertEquals(1, $this->queryBuilder->findDepth($child));
    $last = end($children);
    $this->assertEquals($sibling->id(), $last->getId());
    $this->assertEquals($this->getEntityRevisionId($sibling), $last->getRevisionId());
    $this->assertEquals(1, $this->queryBuilder->findDepth($sibling));
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
   * Gets children of a given node.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $parent
   *   Parent node.
   *
   */
  protected function getChildren(ContentEntityInterface $parent): RecordCollection {
    return $this->queryBuilder->findChildren($parent);
  }

  protected function getDescendants(ContentEntityInterface $parent, $depth = 0, $start = 1): array {
    $descendants = $this->queryBuilder->findDescendants($parent, $depth, $start);
    return $descendants->map(fn (Record $record) => $record->getEntity());
  }

  /**
   * Asserts children in given order.
   *
   * @param \PNX\NestedSet\Node $parent_node
   *   Parent node.
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   Array of entities keyed by label.
   * @param string[] $order
   *   Array of titles in order.
   *
   * @return \PNX\NestedSet\Node[]
   *   Children.
   */
  protected function assertChildOrder(ContentEntityInterface $parent, array $entities, array $order) {
    $children = $this->getChildren($parent);
    $this->assertCount(count($order), $children);
    $this->assertEquals(array_map(function ($name) use ($entities) {
      return $entities[$name]->id();
    }, $order), $children->map(fn (Record $node) => $node->getId()));
  }

}
