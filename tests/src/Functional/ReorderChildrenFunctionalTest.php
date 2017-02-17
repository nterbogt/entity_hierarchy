<?php

namespace Drupal\Tests\entity_hierarchy\Functional;

use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\simpletest\BlockCreationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\entity_hierarchy\EntityHierarchyTestTrait;
use PNX\NestedSet\Node;

/**
 * Defines a class for testing the reorder children form.
 *
 * @group entity_hierarchy
 */
class ReorderChildrenFunctionalTest extends BrowserTestBase {

  use EntityHierarchyTestTrait;
  use BlockCreationTrait;

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
    'block',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->setupEntityHierarchyField(static::ENTITY_TYPE, static::ENTITY_TYPE, static::FIELD_NAME);
    $this->additionalSetup();
    $this->placeBlock('local_tasks_block');
  }

  /**
   * Tests ordered storage in nested set tables.
   */
  public function testReordering() {
    $entities = $this->createChildEntities($this->parent->id());
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
    $entities[$name] = $this->createTestEntity($this->parent->id(), $name, -2);
    $children = $this->treeStorage->findChildren($root_node->getNodeKey());
    $this->assertCount(6, $children);
    $this->assertEquals(array_map(function ($name) use ($entities) {
      return $entities[$name]->id();
    }, ['Child 5', 'Child 4', 'Child 3', 'Child 6', 'Child 2', 'Child 1']), array_map(function (Node $node) {
      return $node->getId();
    }, $children));
    // Now we visit the form for reordering.
    $this->drupalGet($this->parent->toUrl('entity_hierarchy_reorder'));
    $assert = $this->assertSession();
    // Access denied.
    $assert->statusCodeEquals(403);
    // Now login.
    $this->drupalLogin($this->drupalCreateUser(['reorder entity_hierarchy children', 'view test entity']));
    $this->drupalGet($this->parent->toUrl('entity_hierarchy_reorder'));
    $assert->statusCodeEquals(200);
    foreach ($entities as $entity) {
      $assert->linkExists($entity->label());
    }
    // Now move Child 6 to the top.
    $this->submitForm([
      'children[' . $entities[$name]->id() . '][weight]' => -10,
    ], 'Update child order');
    $children = $this->treeStorage->findChildren($root_node->getNodeKey());
    $this->assertCount(6, $children);
    $this->assertEquals(array_map(function ($name) use ($entities) {
      return $entities[$name]->id();
    }, ['Child 6', 'Child 5', 'Child 4', 'Child 3', 'Child 2', 'Child 1']), array_map(function (Node $node) {
      return $node->getId();
    }, $children));
    $this->drupalGet($this->parent->toUrl());
    $assert->linkExists('Children');
    $different_test_entity = EntityTestRev::create(['type' => 'entity_test_rev', 'label' => 'No children here']);
    $different_test_entity->save();
    $this->drupalGet($different_test_entity->toUrl());
    $assert->linkNotExists('Children');
    $this->drupalGet($different_test_entity->toUrl('entity_hierarchy_reorder'));
    // No field, should be access denied here.
    $assert->statusCodeEquals(403);
  }

}
