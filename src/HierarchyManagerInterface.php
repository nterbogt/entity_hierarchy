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
   * Builds the elements of the hierarchy form to be included on the node form.
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
  public function addHierarchyFormElement(array $form, FormStateInterface $form_state, NodeInterface $node, AccountInterface $account, $collapsed = TRUE);

  /**
   * Determines if a given node type is allowed to be a child node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object contains the node type, and the type is used to determine
   *   if this node can be a child of any parent.
   * @return integer
   *   The number of allowed child types for a given node type.
   *
   * @see \Drupal\nodehierarchy\HierarchyManagerInterface::hierarchyGetAllowedParentTypes
   */
  public function hierarchyCanBeChild(NodeInterface $node);

  /**
   * Determines if a given node type is allowed to be a parent node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object contains the node type, and the type is used to determine
   *   if this node can be a parent of any child.
   * @return integer
   *   The number of allowed parent types for a given node type.
   *
   * @see \Drupal\nodehierarchy\HierarchyManagerInterface::hierarchyGetAllowedChildTypes
   */
  public function hierarchyCanBeParent(NodeInterface $node);

  /**
   * Get the allowed parent node types for the given child node type. This
   * method uses configuration management to retrieve the hierarchy settings for
   * allowed parent types based on the child node type.
   *
   * @param int/null $child_type
   *   The child node type.
   *
   * @return array
   *   An array of parent node types allowed for a given child node type.
   */
  public function hierarchyGetAllowedParentTypes($child_type = NULL);

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
   */
  public function hierarchyGetAllowedChildTypes($parent_type);

  /**
   * Loads multiple hierarchy entries.
   *
   * The entries of a hierarchy entry is documented in
   * \Drupal\nodehierarchy\HierarchyOutlineStorageInterface::loadHierarchies.
   *
   * @param int[] $nids
   *   An array of nids to load.
   *
   * @return array[]
   *   The parent ID(s)
   *
   * @see \Drupal\nodehierarchy\HierarchyOutlineStorageInterface::loadHierarchies
   */
  public function loadHierarchy($nids);

  public function updateHierarchy($item);

}