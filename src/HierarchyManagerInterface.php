<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\HierarchyManagerInterface.
 */

namespace Drupal\nodehierarchy;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;


/**
 * Provides an interface defining a hierarchy manager.
 */
interface HierarchyManagerInterface {

  /**
   * Gets the data structure representing a named menu tree.
   *
   * Since this can be the full tree including hidden items, the data returned
   * may be used for generating an an admin interface or a select.
   *
   * @param int $hid
   *   The Hierarchy ID to find links for.
   * @param array|null $link
   *   (optional) A fully loaded menu link, or NULL. If a link is supplied, only
   *   the path to root will be included in the returned tree - as if this link
   *   represented the current page in a visible menu.
   * @param int|null $max_depth
   *   (optional) Maximum depth of links to retrieve. Typically useful if only
   *   one or two levels of a sub tree are needed in conjunction with a non-NULL
   *   $link, in which case $max_depth should be greater than $link['depth'].
   *
   * @return array
   *   An tree of menu links in an array, in the order they should be rendered.
   *
   * Note: based on menu_tree_all_data().
   */
  public function hierarchyTreeAllData($hid, $link = NULL, $max_depth = NULL);

  /**
   * Gets the active trail IDs for the specified hierarchy at the provided path.
   *
   * @param string $hid
   *   The Hierarchy ID to find links for.
   * @param array $link
   *   A fully loaded menu link.
   *
   * @return array
   *   An array containing the active trail: a list of mlids.
   */
  public function getActiveTrailIds($hid, $link);

  /**
   * Loads a single hierarchy entry.
   *
   * @param int $nid
   *   The node ID of the hierarchy.
   * @param bool $translate
   *   If TRUE, set access, title, and other elements.
   *
   * @return array
   *   The hierarchy data of that node.
   */
  public function loadHierarchyLink($nid, $translate = TRUE);

  /**
   * Loads multiple hierarchy entries.
   *
   * @param int[] $nids
   *   An array of nids to load.
   *
   * @param bool $translate
   *   If TRUE, set access, title, and other elements.
   *
   * @return array[]
   *   The hierarchy data of each node keyed by NID.
   */
  public function loadHierarchyLinks($nids, $translate = TRUE);

  /**
   * Returns an array of hierarchy pages in table of contents order.
   *
   * @param int $hid
   *   The ID of the hierarchy whose pages are to be listed.
   * @param int $depth_limit
   *   Any link deeper than this value will be excluded (along with its
   *   children).
   * @param array $exclude
   *   (optional) An array of menu link ID values. Any link whose menu link ID
   *   is in this array will be excluded (along with its children). Defaults to
   *   an empty array.
   *
   * @return array
   *   An array of (menu link ID, title) pairs for use as options for selecting
   *   a hierarchy page.
   */
  public function getTableOfContents($hid, $depth_limit, array $exclude = array());

  /**
   * Finds the depth limit for items in the parent select.
   *
   * @param array $hierarchy_link
   *   A fully loaded menu link that is part of the hierarchy hierarchy.
   *
   * @return int
   *   The depth limit for items in the parent select.
   */
  public function getParentDepthLimit(array $hierarchy_link);

  /**
   * Collects node links from a given menu tree recursively.
   *
   * @param array $tree
   *   The menu tree you wish to collect node links from.
   * @param array $node_links
   *   An array in which to store the collected node links.
   */
  public function hierarchyTreeCollectNodeLinks(&$tree, &$node_links);

  /**
   * Provides hierarchy loading, access control and translation.
   *
   * @param array $link
   *   A hierarchy link.
   *
   * Note: copied from _menu_link_translate() in menu.inc, but reduced to the
   * minimal code that's used.
   */
  public function hierarchyLinkTranslate(&$link);

  /**
   * Gets the hierarchy for a page and returns it as a linear array.
   *
   * @param array $hierarchy_link
   *   A fully loaded hierarchy link that is part of the hierarchy hierarchy.
   *
   * @return array
   *   A linear array of hierarchy links in the order that the links are shown in the
   *   hierarchy, so the previous and next pages are the elements before and after the
   *   element corresponding to the current node. The children of the current node
   *   (if any) will come immediately after it in the array, and links will only
   *   be fetched as deep as one level deeper than $hierarchy_link.
   */
  public function hierarchyTreeGetFlat(array $hierarchy_link);

  /**
   * Returns an array of all hierarchies.
   *
   * This list may be used for generating a list of all the hierarchies, or for
   * building the options for a form select.
   *
   * @return array
   *   An array of all hierarchies.
   */
  public function getAllHierarchies();

  /**
   * Handles additions and updates to the hierarchy outline.
   *
   * This common helper function performs all additions and updates to the hierarchy
   * outline through node addition, node editing, node deletion, or the outline
   * tab.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node that is being saved, added, deleted, or moved.
   *
   * @return bool
   *   TRUE if the hierarchy link was saved; FALSE otherwise.
   */
  public function updateOutline(NodeInterface $node);

  /**
   * Saves a single hierarchy entry.
   *
   * @param array $link
   *   The link data to save.
   * @param bool $new
   *   Is this a new hierarchy.
   *
   * @return array
   *   The hierarchy data of that node.
   */
  public function saveHierarchyLink(array $link, $new);

  /**
   * Returns an array with default values for a hierarchy page's menu link.
   *
   * @param string|int $nid
   *   The ID of the node whose menu link is being created.
   *
   * @return array
   *   The default values for the menu link.
   */
  public function getLinkDefaults($nid);

  public function getHierarchyParents(array $item, array $parent = array());

  /**
   * Builds the common elements of the hierarchy form for the node and outline forms.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\node\NodeInterface $node
   *   The node whose form is being viewed.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account viewing the form.
   * @param bool $collapsed
   *   If TRUE, the fieldset starts out collapsed.
   *
   * @return array
   *   The form structure, with the hierarchy elements added.
   */
  public function addFormElements(array $form, FormStateInterface $form_state, NodeInterface $node, AccountInterface $account, $collapsed = TRUE);

  /**
   * Deletes node's entry from hierarchy table.
   *
   * @param int $nid
   *   The nid to delete.
   */
  public function deleteFromHierarchy($nid);

  /**
   * Returns a rendered menu tree.
   *
   * The menu item's LI element is given one of the following classes:
   * - expanded: The menu item is showing its submenu.
   * - collapsed: The menu item has a submenu which is not shown.
   * - leaf: The menu item has no submenu.
   *
   * @param array $tree
   *   A data structure representing the tree as returned from buildHierarchyOutlineData.
   *
   * @return array
   *   A structured array to be rendered by drupal_render().
   *
   * @todo This was copied from menu_tree_output() but with some changes that
   *   may be obsolete. Attempt to resolve the differences.
   */
  public function hierarchyTreeOutput(array $tree);

  /**
   * Checks access and performs dynamic operations for each link in the tree.
   *
   * @param array $tree
   *   The hierarchy tree you wish to operate on.
   * @param array $node_links
   *   A collection of node link references generated from $tree by
   *   menu_tree_collect_node_links().
   */
  public function hierarchyTreeCheckAccess(&$tree, $node_links = array());

  /**
   * Gets the data representing a subtree of the hierarchy hierarchy.
   *
   * The root of the subtree will be the link passed as a parameter, so the
   * returned tree will contain this item and all its descendants in the menu
   * tree.
   *
   * @param array $link
   *   A fully loaded hierarchy link.
   *
   * @return
   *   A subtree of hierarchy links in an array, in the order they should be rendered.
   */
  public function hierarchySubtreeData($link);

  /**
   * Determines if a node can be removed from the hierarchy.
   *
   * A node can be removed from a hierarchy if it is actually in a hierarchy and it either
   * is not a top-level page or is a top-level page with no children.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to remove from the outline.
   *
   * @return bool
   *   TRUE if a node can be removed from the hierarchy, FALSE otherwise.
   */
  public function checkNodeIsRemovable(NodeInterface $node);

}
