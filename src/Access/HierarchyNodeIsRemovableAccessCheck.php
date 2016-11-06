<?php

/**
 * @file
 * Contains Drupal\entity_hierarchy\Access\HierarchyNodeIsRemovableAccessCheck.
 */

namespace Drupal\entity_hierarchy\Access;

use Drupal\entity_hierarchy\HierarchyManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\node\NodeInterface;

/**
 * Determines whether the requested node can be removed from its entity_hierarchy.
 */
class HierarchyNodeIsRemovableAccessCheck implements AccessInterface{

  /**
   * Hierarchy Manager Service.
   *
   * @var \Drupal\entity_hierarchy\HierarchyManagerInterface
   */
  protected $entity_hierarchyManager;

  /**
   * Constructs a HierarchyNodeIsRemovableAccessCheck object.
   *
   * @param \Drupal\entity_hierarchy\HierarchyManagerInterface $entity_hierarchy_manager
   *   Hierarchy Manager Service.
   */
  public function __construct(HierarchyManagerInterface $entity_hierarchy_manager) {
    $this->entity_hierarchyManager = $entity_hierarchy_manager;
  }

  /**
   * Checks access for removing the node from its entity_hierarchy.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node requested to be removed from its entity_hierarchy.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node) {
    return AccessResult::allowedIf($this->entity_hierarchyManager->checkNodeIsRemovable($node))->cacheUntilEntityChanges($node);
  }

}
