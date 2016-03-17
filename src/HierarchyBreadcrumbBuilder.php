<?php

/**
 * @file
 * Contains \Drupal\book\HierarchyBreadcrumbBuilder.
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
//use Drupal\Core\Entity\EntityInterface;
//use Drupal\Core\Url;

/**
 * Provides a breadcrumb builder for nodes in a book.
 */
class HierarchyBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Constructs the HierarchyBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   The access manager.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   */
  public function __construct(EntityManagerInterface $entity_manager, AccessManagerInterface $access_manager, AccountInterface $account) {
    $this->nodeStorage = $entity_manager->getStorage('node');
    $this->accessManager = $access_manager;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $node = $route_match->getParameter('node');
    return $node instanceof NodeInterface && !empty($node->book);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {

    $breadcrumb = new Breadcrumb();
    $links = array(Link::createFromRoute($this->t('Home'), '<front>'));

    // Get all the possible breadcrumbs for the node.
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->nid->value;
    $links = $this->hierarchyGetBreadcrumb($nid, $links);

    // Remove the node itself if it's not needed (we would want it for the children tab for example).
    // Need to figure out what's going on here and fix the following
    $add_node=false;
    if ($add_node) {
//      $uri = Url::fromUri('node', $node);
      $options = array();
      $links[] = $node->link($node->getTitle(), 'canonical', $options)->getGeneratedLink();
//      $links[] = array(Link::createFromRoute($node->title, $uri['path'], $uri['options']));
    }

    // Stick the home link on the top of the breadcrumb.
//    array_unshift($breadcrumb, Link::createFromRoute(t('Home'), '<front>'));

//    drupal_set_breadcrumb($breadcrumb);



//    $links = array(Link::createFromRoute($this->t('Home'), '<front>'));
//    $hierarchy = $route_match->getParameter('node')->book;
//    $depth = 1;
//    // We skip the current node.
//    while (!empty($hierarchy['p' . ($depth + 1)])) {
//      $hierarchy_nids[] = $hierarchy['p' . $depth];
//      $depth++;
//    }
//    $parent_books = $this->nodeStorage->loadMultiple($hierarchy_nids);
//    if (count($parent_books) > 0) {
//      $depth = 1;
//      while (!empty($hierarchy['p' . ($depth + 1)])) {
//        if (!empty($parent_books[$hierarchy['p' . $depth]]) && ($parent_book = $parent_books[$hierarchy['p' . $depth]])) {
//          $access = $parent_book->access('view', $this->account, TRUE);
//          $breadcrumb->addCacheableDependency($access);
//          if ($access->isAllowed()) {
//            $breadcrumb->addCacheableDependency($parent_book);
//            $links[] = Link::createFromRoute($parent_book->label(), 'entity.node.canonical', array('node' => $parent_book->id()));
//          }
//        }
//        $depth++;
//      }
//    }
    dpm($links);
    $breadcrumb->setLinks($links);
//    $breadcrumb->addCacheContexts(['route.hierarchy_navigation']);
    return $breadcrumb;
  }

  /**
   * Get the breadcrumbs for the given node.
   *
   * There could be multiple breadcrumbs because there could be multiple parents.
   */
  private function hierarchyGetBreadcrumb($nid, $links) {

    // Retrieve the descendant list of menu links and convert them to a breadcrumb trail.
    $trail = $this->hierarchyGetNodePrimaryAncestorNodes($nid);
    foreach ($trail as $node) {
//      $uri = entity_uri('node', $node);
//      $breadcrumb[] = l($node->title, $uri['path'], $uri['options']);
      $options = array();
      $links[] = $node->link($node->getTitle(), 'canonical', $options)->getGeneratedLink();
    }
    return $links;
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
