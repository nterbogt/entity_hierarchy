<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\HierarchyOutlineStorageInterface.
*/

namespace Drupal\nodehierarchy;

/**
 * Defines a common interface for hierarchy outline storage classes.
 */
interface HierarchyOutlineStorageInterface {

  /**
   * Gets hierarchies (the highest positioned hierarchy links).
   *
   * @return array
   *   An array of hierarchy IDs.
   */
  public function getHierarchies();

  /**
   * Checks if there are any hierarchies.
   *
   * @return bool
   *   TRUE if there are hierarchies, FALSE if not.
   */
  public function hasHierarchies();

  /**
   * Loads hierarchies.
   *
   * @param array $nids
   *   An array of node IDs.
   *
   * @return array
   *   Array of loaded hierarchy items.
   */
  public function loadMultiple($nids);

  /**
   * Gets child relative depth.
   *
   * @param array $hierarchy_link
   *   The hierarchy link.
   *
   * @param int $max_depth
   *   The maximum supported depth of the hierarchy tree.
   *
   * @return int
   *   The depth of the searched hierarchy.
   */
  public function getChildRelativeDepth($hierarchy_link, $max_depth);

  /**
   * Deletes a hierarchy entry.
   *
   * @param int $nid
   *   Deletes a hierarchy entry.
   *
   * @return mixed
   *   Number of deleted hierarchy entries.
   */
  public function delete($nid);

  /**
   * Loads hierarchy's children using it's parent ID.
   *
   * @param int $pid
   *   The hierarchy's parent ID.
   *
   * @return array
   *   Array of loaded hierarchy items.
   */
  public function loadHierarchyChildren($pid);

  /**
   * Builds tree data used for the menu tree.
   *
   * @param int $bid
   *   The ID of the hierarchy that we are building the tree for.
   * @param array $parameters
   *   An associative array of build parameters. For info about individual
   *   parameters see HierarchyManager::hierarchyTreeBuild().
   * @param int $min_depth
   *   The minimum depth of hierarchy links in the resulting tree.
   * @param int $max_depth
   *   The maximum supported depth of the hierarchy tree.
   *
   * @return array
   *   Array of loaded hierarchy links.
   */
  public function getHierarchyMenuTree($bid, $parameters, $min_depth, $max_depth);

  /**
   * Inserts a hierarchy link.
   *
   * @param array $link
   *   The link array to be inserted in the database.
   * @param array $parents
   *   The array of parent ids for the link to be inserted.
   *
   * @return mixed
   *   The last insert ID of the query, if one exists.
   */
  public function insert($link, $parents);


  /**
   * Updates hierarchy reference for links that were moved between hierarchies.
   *
   * @param int $nid
   *   The nid of the hierarchy entry to be updated.
   * @param array $fields
   *   The array of fields to be updated.
   *
   * @return mixed
   *   The number of rows matched by the update query.
   */
  public function update($nid, $fields);

  /**
   * Update the hierarchy ID of the hierarchy link that it's being moved.
   *
   * @param int $bid
   *   The ID of the hierarchy whose children we move.
   * @param array $original
   *   The original parent of the hierarchy link.
   * @param array $expressions
   *   Array of expressions to be added to the query.
   * @param int $shift
   *   The difference in depth between the old and the new position of the
   *   element being moved.
   *
   * @return mixed
   *   The number of rows matched by the update query.
   */
  public function updateMovedChildren($bid, $original, $expressions, $shift);

  /**
   * Count the number of original link children.
   *
   * @param array $original
   *   The hierarchy link array.
   *
   * @return int
   *   Number of children.
   */
  public function countOriginalLinkChildren($original);

  /**
   * Get hierarchy subtree.
   *
   * @param array $link
   *   A fully loaded hierarchy link.
   * @param int $max_depth
   *   The maximum supported depth of the hierarchy tree.
   *
   * @return array
   *   Array of unordered subtree hierarchy items.
   */
  public function getHierarchySubtree($link, $max_depth);
}
