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
   *
   * @see entity_hierarchy_form_node_form_alter
   */
  public function addHierarchyFormElement(array $form, FormStateInterface $form_state, NodeInterface $node, AccountInterface $account, $collapsed = TRUE);

  /**
   * Create the hierarchy type settings form, and load any default values using
   * the configuration management settings.
   *
   * @param string $key
   *   The name of the node type for which the form is being built.
   * @param bool $append_key
   *   Append a node type where appropriate (almost always)
   * @return array
   *   The form array for the given hierarchy type.
   *
   * @see entity_hierarchy_form_node_type_edit_form_alter
   */
  public function hierarchyGetNodeTypeSettingsForm($key, $append_key = FALSE);

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
  public function hierarchyGetAllowedChildTypes($parent_type);

  /**
   * Create a default object for a new hierarchy item.
   *
   * @param int/null $cnid
   *   The child node id.
   * @param int/null $pnid
   *   The parent node id.
   * @return object
   *   The new hierarchy item.
   *
   * @see entity_hierarchy_node_prepare_form
   */
  public function hierarchyDefaultRecord($cnid = NULL, $pnid = NULL);

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
   * Process a list of entity_hierarchy parents in preparation for writing to the
   * database. No permission checking is done here. Each parent is written
   * individually using HierarchyManager::hierarchyRecordSave.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object containing the list of parents to process.
   *
   * @see HierarchyManager::hierarchyRecordSave
   */
  public function hierarchySaveNode(&$node);

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