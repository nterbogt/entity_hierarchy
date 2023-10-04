<?php

namespace Drupal\Tests\entity_hierarchy\Kernel;

use Drupal\Core\Url;

/**
 * Tests children route context and permissions.
 *
 * @group entity_hierarchy.
 */
class RouteContextTest extends EntityHierarchyKernelTestBase {

  const FIELD_NAME = 'parents';
  const ENTITY_TYPE = 'entity_test';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::service('router.builder')->rebuild();
    $this->drupalSetUpCurrentUser();
  }

  /**
   * Tests route context.
   */
  public function testRouteContext(): void {
    $route = Url::fromRoute('entity.' . static::ENTITY_TYPE . '.entity_hierarchy_reorder', [
      static::ENTITY_TYPE => $this->parent->id(),
    ]);

    $user = $this->createUser([
      'view test entity',
    ]);
    $this->assertFalse($route->access($user));

    $user_allowed = $this->createUser([
      'view test entity',
      'reorder entity_hierarchy children',
    ]);
    $this->assertTrue($route->access($user_allowed));
  }

}
