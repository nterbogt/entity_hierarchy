<?php

namespace Drupal\Tests\entity_hierarchy\Kernel;

use Drupal\views\Tests\ViewResultAssertionTrait;
use Drupal\views\Views;

/**
 * Defines a class for testing views integration.
 *
 * @group entity_hierarchy
 */
class ViewsIntegrationTest extends EntityHierarchyKernelTestBase {

  use ViewResultAssertionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views', 'entity_hierarchy_test_views'];

  /**
   * {@inheritdoc}
   */
  protected function additionalSetup() {
    parent::additionalSetup();
    $this->installConfig('entity_hierarchy_test_views');
    $this->installConfig('system');
    $this->installSchema('system', ['router', 'sequences', 'key_value_expire']);
  }

  /**
   * Tests views integration.
   */
  public function testViewsIntegration() {
    $child = $this->createTestEntity($this->parent->id(), 'Child 1');
    $this->createChildEntities($child->id(), 5);
    $executable = Views::getView('entity_hierarchy_test_children_view');
    $executable->preview();
    $this->assertIdenticalResultset($executable, [], []);
  }

}
