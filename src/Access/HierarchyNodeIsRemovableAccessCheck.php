<?php

/**
 * @file
 * Contains Drupal\nodehierarchy\Access\HierarchyNodeIsRemovableAccessCheck.
 */

namespace Drupal\nodehierarchy\Access;

use Drupal\nodehierarchy\HierarchyManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\node\NodeInterface;

/**
 * Determines whether the requested node can be removed from its nodehierarchy.
 */
class HierarchyNodeIsRemovableAccessCheck implements AccessInterface{

  /**
   * Hierarchy Manager Service.
   *
   * @var \Drupal\nodehierarchy\HierarchyManagerInterface
   */
  protected $nodehierarchyManager;

  /**
   * Constructs a HierarchyNodeIsRemovableAccessCheck object.
   *
   * @param \Drupal\nodehierarchy\HierarchyManagerInterface $nodehierarchy_manager
   *   Hierarchy Manager Service.
   */
  public function __construct(HierarchyManagerInterface $nodehierarchy_manager) {
    $this->nodehierarchyManager = $nodehierarchy_manager;
  }

  /**
   * Checks access for removing the node from its nodehierarchy.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node requested to be removed from its nodehierarchy.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node) {
    return AccessResult::allowedIf($this->nodehierarchyManager->checkNodeIsRemovable($node))->cacheUntilEntityChanges($node);
  }

}
