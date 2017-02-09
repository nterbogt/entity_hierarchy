<?php

namespace Drupal\Tests\entity_hierarchy\Kernel;

use Console_Table;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PNX\NestedSet\Node;

/**
 * Tests integration with entity_hierarchy and a revisionable entity.
 *
 * @group entity_hierarchy
 */
class HierarchyNestedSetRevisionIntegrationTest extends HierarchyNestedSetIntegrationTest {

  /**
   * {@inheritdoc}
   */
  const ENTITY_TYPE = 'entity_test_rev';

  /**
   * {@inheritdoc}
   */
  protected function additionalSetup() {
    $this->installEntitySchema('entity_test');
    parent::additionalSetup();
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
    $entity = EntityTestRev::create($values);
    $entity->setNewRevision(TRUE);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function createTestEntity($parentId, $label = 'Child 1', $weight = 0, $withRevision = TRUE) {
    $entity = parent::createTestEntity($parentId, $label, $weight);
    if ($withRevision) {
      // Save it twice with another revision.
      $entity->setNewRevision(TRUE);
      $entity->save();
    }
    return $entity;
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
    $this->assertEquals($this->getEntityRevisionId($child), $first->getRevisionId());
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
    $children = $this->treeStorage->findChildren($root_node->getNodeKey());
    // Trim down to just the current revisions.
    $children = $this->getChildren($root_node);
    $this->assertCount(2, $children);
    $first = reset($children);
    $this->assertEquals($child->id(), $first->getId());
    $this->assertEquals($this->getEntityRevisionId($child), $first->getRevisionId());
    $this->assertEquals(1, $first->getDepth());
    $last = end($children);
    $this->assertEquals($sibling->id(), $last->getId());
    $this->assertEquals($this->getEntityRevisionId($sibling), $last->getRevisionId());
    $this->assertEquals(1, $last->getDepth());
  }

  /**
   * {@inheritdoc}
   */
  protected function doCreateChildTestEntity($parentId, $label, $weight) {
    // Don't want revisions.
    return $this->createTestEntity($parentId, $label, $weight, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  protected function getChildren($parent_node) {
    // Limit to latest revisions only.
    $children = parent::getChildren($parent_node);
    $entity_storage = $this->container->get('entity_type.manager')->getStorage(static::ENTITY_TYPE);
    $entities = $entity_storage->loadMultiple();
    $revisions = array_map(function (EntityInterface $entity) {
      return $entity->getRevisionId();
    }, $entities);
    $children = array_filter($children, function (Node $node) use ($revisions) {
      return in_array($node->getRevisionId(), $revisions, TRUE);
    });
    return $children;
  }
}
