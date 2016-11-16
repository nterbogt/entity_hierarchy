<?php

/**
 * @file
 * Contains \Drupal\entity_hierarchy\HierarchyBaseInterface.
 *
 * Provides a basic entity hierarchy building block object.
 *
 * As of right now, the rules are as follows:
 * 1) An entity hierarchy object is the base object upon which everything else
 *    is built from a hierarchy perspective.
 * 2) An entity hierarchy object may have zero or one parent.
 * 3) An entity hierarchy object may have zero or more children.
 * 4) An entity hierarchy object is identified by an ID. The parent and
 *    children are also identified by IDs. We do check for uniqueness of IDs
 *    at this level. Children must have a weight in addition to an ID.
 * 5) We have no knowledge of Drupal whatsoever at this level.
 * 6) We have no knowledge of other entity hierarchy objects. It will be up to
 *    a class at a higher level to ensure uniqueness etc.
 */

namespace Drupal\entity_hierarchy;

/**
 * Provides an interface for creating, updating, and deleting a hierarchy.
 */
interface HierarchyBaseInterface {

  /**
   * Set the ID of the hierarchy object.
   *
   * @param int/null $hid
   *   The ID to set as the hierarchy object ID.
   */
  public function setHierarchyId($hid);

  /**
   * Get the ID of the hierarchy object.
   *
   * @return int/null
   *   The entity hierarchy object ID.
   */
  public function getHierarchyId();

  /**
   * Set the parent ID.
   *
   * @param int/null $pid
   *   The ID identifying the parent of this entity hierarchy object.
   */
  public function setParent($pid);

  /**
   * Get the parent ID of this entity object.
   *
   * @return int/null
   *   The parent's ID.
   */
  public function getParent();

  /**
   * Unsets the parent ID from the hierarchy object.
   */
  public function removeParent();

  /**
   * Add a new child to the hierarchy.
   *
   * @param int $cid
   *   The child ID of the new child.
   *
   * @param int $weight
   *   The weight value to be set.
   */
  public function addChild($weight, $cid);

  /**
   * Sets the children array.
   *
   * @param array $children
   *   The array of children with the key being the weight and the value being
   *   the child ID.
   */
  public function setChildren($children);

  /**
   * Get the children of the entity hierarchy object.
   *
   * @return array
   *   A list of children/weights.
   */
  public function getChildren();

  /**
   * Removes one child from the children array by weight (key).
   *
   * @param int $weight
   *   The weight of the child to be removed.
   */
  public function removeChildByWeight($weight);

  /**
   * Removes one child from the children array by child ID (value).
   *
   * @param int $cid
   *   The child ID of the child to be removed.
   */
  public function removeChildById($cid);

  /**
   * Deletes the children array.
   */
  public function deleteChildren();

  /**
   * Sorts the children by weight (key).
   */
  public function sortChildren();


  /**
   * Set the boolean value for can have parent.
   *
   * @param bool $can_have_parent
   *   If true, the entity object is allowed to define a parent.
   */
  public function setCanHaveParent($can_have_parent);

  /**
   * Gets the can have children boolean setting.
   *
   * @return bool
   */
  public function getCanHaveChildren();

  /**
   * Set the boolean value for can have children.
   *
   * @param bool $can_have_parent
   *   If true, the entity object is allowed to add children.
   */
  public function setCanHaveChildren($can_have_parent);

  /**
   * Given a parent object, does it have children?
   *
   * @return bool
   *   True if the object has at least one child.
   */
  public function hasChild();

  /**
   * Given a child object, does it have children?
   *
   * @return bool
   *   True if the object has a parent.
   */
  public function hasParent();

}
