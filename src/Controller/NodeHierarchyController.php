<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\Controller\NodeHierarchyController.
 */

namespace Drupal\nodehierarchy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use \Drupal\node\Entity\Node;

/**
 * Controller routines for book routes.
 */
class NodeHierarchyController extends ControllerBase {
  public function getTitle() {
    // Is there really no better way to do this?
    $url = \Drupal\Core\Url::fromRoute('<current>');
    $curr_path = $url->toString();
    $path = explode('/', $curr_path);
    $nid = $path[2];

    $node = Node::load($nid);
    if (is_object($node)){
      return t('Children of %t', array('%t' => $node->getTitle()));
    }
  }
}
