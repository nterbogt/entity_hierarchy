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
   * The maximum hierarchy depth
   *
   * @var int
   */
  private $max_hierarchy_depth;

  public function __construct($hid) {
    $this->hid = $hid;
    $this->children = array();
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
    $this->parent_id = $pid;
  }

  /**
   * @inheritdoc
   */
  public function getParent() {
    return $this->parent_id;
  }

  public function removeParent() {
    unset($this->parent_id);
  }

  /**
   * @inheritDoc
   */
  public function setChildren($children) {
    $this->children = $children;
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
  public function addChild($cid, $weight){
    $this->children[$weight] = $cid;
    $this->children = $this->sortChildren();
  }

  public function removeChildByWeight($weight=0) {
    unset($this->children[$weight]);
  }

  public function removeChildById($cid) {
    if(($weight = array_search($cid, $this->children)) !== false) {
      unset($this->children[$weight]);
    }
  }

  public function deleteChildren() {
    unset($this->children);
  }

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
  public function setMaxHierarchyDepth($max_depth) {
    $this->max_hierarchy_depth = $max_depth;
  }

  /**
   * @inheritdoc
   */
  public function getMaxHierarchyDepth() {
    return($this->max_hierarchy_depth);
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