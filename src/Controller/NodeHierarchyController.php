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
  /**
   * Constructs a NodeHierarchyController object.
   */
  public function __construct() {
    // Nothing to do right now; do we need empty constructor anyway?
  }

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

  public function nodeLoad(NodeInterface $node) {
    // This function must return a renderable array or response object.
    // dpm($node);

    dpm("Hello");

    if (\Drupal::currentUser()->hasPermission('create child nodes')
       && (\Drupal::currentUser()->hasPermission('create child of any parent'))) {
      dpm("nodeLoad");
      // || node_access('update', $node))) {

      dpm($node->getType());
//      foreach (nodehierarchy_get_allowed_child_types($node->type) as $key) {
//        if (node_access('create', $key)) {
//          $type_name = node_type_get_name($key);
//          $destination = (array)drupal_get_destination() + array('parent' => $node->nid);
//          $key = str_replace('_', '-', $key);
//          $title = t('Add a new %s.', array('%s' => $type_name));
//          $create_links[] = l($type_name, "node/add/$key", array('query' => $destination, 'attributes' => array('title' => $title)));
//        }
//      }
      //if ($create_links) {
        $out[] = array('#children' => '<div class="newchild">' . t("Create new child !s", array('!s' => implode(" | ", $create_links))) . '</div>');
      //}
      dpm($out);
    }
    return $out;
  }
}
