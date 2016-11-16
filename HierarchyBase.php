<?php

/**
 * @file
 * Contains \Drupal\entity_hierarchy\HierarchyBase.
 *
 * Provides a basic API for creating and editing an entity hierarchy, regardless
 * of the entity type. Includes permission check to for a given action.
 */

namespace Drupal\entity_hierarchy;

/**
 * Provides an interface for creating, updating, and deleting a hierarchy.
 */
class HierarchyBase implements HierarchyBaseInterface {

  /**
   * The hierarchy ID of an entity hierarchy object.
   *
   * @var int
   */
  private $hid;

  /**
   * The child's parent ID of an entity hierarchy object.
   *
   * @var int
   */
  private $parent_id;

  /**
   * The child/children ID(s) of an entity hierarchy object.
   *
   * @var array
   */
  private $children;

  /**
   * Can this object have a parent
   *
   * @var bool
   */
  private $can_have_parent;

  /**
   * Can this object have children
   *
   * @var bool
   */
  private $can_have_children;

  /**
   * The next child weight.
   *
   * @var int
   */
  private $next_child_weight;

  /**
   * Defines the minimum weight of a child (but has the highest priority).
   */
  const HIERARCHY_MIN_CHILD_WEIGHT = -50;

  /**
   * Defines the maximum weight of a child (but has the lowest priority).
   */
  const HIERARCHY_MAX_CHILD_WEIGHT = 50;

  /**
   * HierarchyBase constructor.
   *
   * @param $hid
   *   The hierarchy ID for this hierarchy object.
   *
   * @param bool $can_have_parent
   *   Can have a parent flag.
   *
   * @param bool $can_have_children
   *   Can have children flag.
   */
  public function __construct($hid, $can_have_parent = TRUE, $can_have_children = TRUE) {
    $this->hid = $hid;
    $this->children = array();
    $this->can_have_parent = $can_have_parent;
    $this->can_have_children = $can_have_children;
  }

  /**
   * @inheritdoc
   */
  public function setHierarchyId($hid) {
    $this->hid = $hid;
  }

  /**
   * @inheritdoc
   */
  public function getHierarchyId() {
    return $this->hid;
  }

  /**
   * @inheritdoc
   */
  public function setParent($pid) {
    if ($this->can_have_parent === TRUE) {
      $this->parent_id = $pid;
    }
  }

  /**
   * @inheritdoc
   */
  public function getParent() {
    return $this->parent_id;
  }

  /**
   * @inheritDoc
   */
  public function removeParent() {
    unset($this->parent_id);
  }

  /**
   * @inheritDoc
   */
  public function setChildren($children) {
    if ($this->can_have_children === TRUE) {
      $this->children = $children;
      $this->setNextChildWeight();
    }
  }
  
  /**
   * @inheritdoc
   */
  public function getChildren() {
    return $this->children;
  }

  /**
   * @inheritDoc
   */
  public function addChild($cid){
    $this->setNextChildWeight();
    if ($this->can_have_children === TRUE) {
      $this->children[$this->getNexChildWeight()] = $cid;
      $this->children = $this->sortChildren();
    }
  }

  /**
   * Sets the next child weight.
   *
   * Sets the next child weight based on the minimum weight constant and the
   * count of the children array.
   *
   * @throws \LogicException
   */
  private function setNextChildWeight() {
    $this->next_child_weight = self::HIERARCHY_MIN_CHILD_WEIGHT + count($this->children);
    if ($this->next_child_weight > self::HIERARCHY_MAX_CHILD_WEIGHT) {
      throw new \LogicException("The maximum child weight cannot exceed ".self::HIERARCHY_MAX_CHILD_WEIGHT.".");
    }
  }

  /**
   * @inheritdoc
   */
  public function getNexChildWeight() {
    return $this->next_child_weight;
  }

  /**
   * @inheritDoc
   */
  public function removeChildByWeight($weight) {
    unset($this->children[$weight]);
    $this->setNextChildWeight();
  }

  /**
   * @inheritDoc
   */
  public function removeChildById($cid) {
    if(($weight = array_search($cid, $this->children)) !== false) {
      unset($this->children[$weight]);
      $this->setNextChildWeight();
    }
  }

  /**
   * @inheritDoc
   */
  public function deleteChildren() {
    unset($this->children);
    $this->setNextChildWeight();
  }

  /**
   * @inheritDoc
   */
  public function sortChildren() {
    return(ksort($this->children));
  }

  /**
   * @inheritdoc
   */
  public function setCanHaveParent($can_have_parent) {
    $this->can_have_parent = $can_have_parent;
  }

  /**
   * @inheritdoc
   */
  public function getCanHaveParent() {
    return($this->can_have_parent);
  }

  /**
   * @inheritdoc
   */
  public function setCanHaveChildren($can_have_children) {
    $this->can_have_children = $can_have_children;
  }

  /**
   * @inheritdoc
   */
  public function getCanHaveChildren() {
    return($this->can_have_children);
  }

  /**
   * @inheritdoc
   */
  public function hasChild() {
    return empty($this->children) ? FALSE : TRUE;
  }

  /**
   * @inheritdoc
   */
  public function hasParent() {
    return empty($this->parent_id) ? FALSE : TRUE;
  }
}