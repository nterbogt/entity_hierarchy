<?php

/**
 * @file
 * Contains \Drupal\entity_hierarchy\HierarchyManagerInterface.
 */

namespace Drupal\entity_hierarchy;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;


/**
 * Provides an interface defining a hierarchy manager.
 */
interface HierarchyManagerInterface {

  /**
   * Get the allowed child node types for the given parent node type. This
   * method uses configuration management to retrieve the hierarchy settings for
   * allowed child types based on the parent node type.
   *
   * @param int/null $parent_type
   *   The parent node type.
   *
   * @return array
   *   An array of child node types allowed for a given parent node type.
   *
   * @see hierarchyCanBeParent
   * @see NodehierarchyChildrenForm::form
   */
  public function hierarchyGetAllowedChildTypes($parent_type, $bundle);

  /**
   * Create a default object for a new hierarchy item.
   *
   * @param int/null $hid
   *   The hierarchy ID.
   * @return object
   *   The new hierarchy item.
   *
   * @see entity_hierarchy_node_prepare_form
   */
  public function hierarchyDefaultRecord($hid);

  /**
   * Determines if a given node type is allowed to be a child node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object contains the node type, and the type is used to determine
   *   if this node can be a child of any parent.
   * @return integer
   *   The number of allowed child types for a given node type.
   *
   * @see \Drupal\entity_hierarchy\HierarchyManagerInterface::hierarchyGetAllowedParentTypes
   */
  public function hierarchyCanBeChild(NodeInterface $node);

  /**
   * Determines if a given node type is allowed to be a parent node.
   *
   * @param $node
   *   The node object contains the node type, and the type is used to determine
   *   if this node can be a parent of any child. May pass the node type directly.
   * @return integer
   *   The number of allowed parent types for a given node type.
   *
   * @see HierarchyManager::hierarchyGetAllowedChildTypes
   * @see entity_hierarchy_form_node_form_alter
   */
  public function hierarchyCanBeParent($node);
  
  /**
   * Updates an existing hierarchy record in the database using the
   * HierarchyOutlineStorage class.
   *
   * @param object $item
   *   The hierarchy object to be updated in the database.
   * @return mixed
   *   Todo: figure out what's being returned
   *
   * @see HierarchyOutlineStorage::update
   * @see hierarchyRecordSave
   * @see NodehierarchyChildrenForm::save
   */
  public function updateHierarchy($item);
}