<?php

/**
 * @file
 * Contains \Drupal\book\Controller\BookController.
 */

namespace Drupal\nodehierarchy\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller routines for book routes.
 */
class NodeHierarchyController extends ControllerBase {
  /**
   * Constructs a NodeHierarchyController object.
   */
  public function __construct() {
    // Nothing to do right now; do we need empty constructor anyway?
  }

  /**
   * Returns an administrative for the Node Hierarchy.
   *
   * @return array
   *   A render array representing the administrative page content.
   *
   */
  public function adminOverview() {
    $build = array(
      '#type' => 'markup',
      '#markup' => t('Hello World!'),
    );
    return $build;
  }
}
