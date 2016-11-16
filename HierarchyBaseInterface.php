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
   * Get the children of the entity hierarchy object.
   *
   * @return array
   *   A list of children/weights.
   */
  public function getChildren();

  /**
   * Given a child, is the object passed in allowed to be a parent.
   *
   * @param object $child
   *   The child's ID.
   *
   * @return bool
   *   True if the object is allowed to be a parent.
   */
  public function canBeParent($child);

  /**
   * Given a parent, is the object passed in allowed to be a child.
   *
   * @param object $parent
   *   The parent object.
   *
   * @return bool
   *   True if the object is allowed to be a child.
   */
  public function canBeChild($parent);

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

//  public function setInheritance();
//
//  public function getInheritance();

//  public function canSetHierarchy();
//
//  public function canEditHierarchy();
//
//  public function canDeleteHierarchy();
//
//  public function canAdministerHierarchy();

}
