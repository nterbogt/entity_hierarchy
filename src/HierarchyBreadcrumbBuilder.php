<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\HierarchyBreadcrumbBuilder.
 */

namespace Drupal\nodehierarchy;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Provides a breadcrumb builder for nodes in a book.
 */
class HierarchyBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $node = $route_match->getParameter('node');
    $parent = null;
    if ($node instanceof NodeInterface) {
      $current_nid = $node->id();
      $hierarchy_storage = \Drupal::service('nodehierarchy.outline_storage');
      $parent = $hierarchy_storage->hierarchyGetParent($current_nid);
    }
    return !empty($parent) ? true : false;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {

    // Get all the possible breadcrumbs for the node.
    $node = $route_match->getParameter('node');
    $nid = $node->nid->value;
    $trail = $this->hierarchyGetNodePrimaryAncestorNodes($nid);
    if ($trail) {
      $breadcrumb = new Breadcrumb();
      $links = [Link::createFromRoute($this->t('Home'), '<front>')];
      foreach ($trail as $node) {
        $options = array();
        $links[] = $node->toLink($node->getTitle(), 'canonical', $options);
      }
      $breadcrumb->addCacheableDependency($node);
      $breadcrumb->setLinks($links);
      $breadcrumb->addCacheContexts(['route']);
      return $breadcrumb;
    }
    else {
      return;
    }

  }

  /**
   * Get the parent nodes for the given node.
   */
  private function hierarchyGetNodePrimaryAncestorNodes($nid) {
    return \Drupal\node\Entity\Node::loadMultiple($this->hierarchyGetNodePrimaryAncestorNids($nid));
  }

  /**
   * Get the ancestor nodes for the given node.
   *
   * @TODO: make this more efficient by implementing a materialized path or similar.
   */
  private function hierarchyGetNodePrimaryAncestorNids($nid) {
    $out = array();
    if ($parent_nid = $this->hierarchyGetNodeParentPrimaryNid($nid)) {
      $out = $this->hierarchyGetNodePrimaryAncestorNids($parent_nid);
      $out[] = $parent_nid;
    }
    return $out;
  }

  /**
   * Get the primary parent nid for the given node.
   */
  private function hierarchyGetNodeParentPrimaryNid($nid) {
    $nids = $this->hierarchyGetNodeParentNids($nid, 1);
    return current($nids);
  }

  /**
   * Get the parent nodes for the given node.
   */
  private function hierarchyGetNodeParentNids($node, $limit = NULL) {
    $out = array();
    foreach ($this->hierarchyGetNodeParents($node, $limit) as $parent) {
      // This may be an array after a node has been submitted.
      $parent = (object)$parent;
      $out[] = $parent->pnid;
    }
    return $out;
  }

  /**
   * Get all the parents for the given node.
   */
  private function hierarchyGetNodeParents($node, $limit = NULL) {
    $cnid = $node;
    // If a node object was passed, then the parents may already have been loaded.
    if (is_object($node)) {
      if (isset($node->nodehierarchy_parents)) {
        return $node->nodehierarchy_parents;
      }
      $cnid = $node->nid;
    }
    $out = array();
    $db = \Drupal::database();
    $query = $db->select('nodehierarchy', 'nh')
      ->fields('nh')
      ->where('cnid = :cnid', array(':cnid' => $cnid))
      ->orderBy('pweight', 'ASC');

    if ($limit) {
      $query->range(0, $limit);
    }
    $result = $query->execute()->fetchAll();
    foreach ($result as $item) {
      $out[] = $item;
    }
    return $out;
  }

}
