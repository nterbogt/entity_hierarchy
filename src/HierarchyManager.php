<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\HierarchyManager.
 */

namespace Drupal\nodehierarchy;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\NodeInterface;

/**
 * Defines a hierarchy manager.
 */
class HierarchyManager implements HierarchyManagerInterface {
  use StringTranslationTrait;

  /**
   * Defines the maximum supported depth of a given hierarchy tree.
   */
  const HIERARCHY_MAX_DEPTH = 9;

  /**
   * Entity manager Service Object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * Config Factory Service Object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Hierarchies Array.
   *
   * @var array
   */
  protected $hierarchies;

  /**
   * Hierarchy outline storage.
   *
   * @var \Drupal\nodehierarchy\HierarchyOutlineStorageInterface
   */
  protected $hierarchyOutlineStorage;

  /**
   * Stores flattened hierarchy trees.
   *
   * @var array
   */
  protected $hierarchyTreeFlattened;

  /**
   * Constructs a HierarchyManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   Provides an interface for entity type managers.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   Interface for the translation.manager translation service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Defines the interface for a configuration object factory.
   * @param \Drupal\nodehierarchy\HierarchyOutlineStorageInterface $hierarchy_outline_storage
   *   The entity hierarchy storage object
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, TranslationInterface $translation, ConfigFactoryInterface $config_factory, HierarchyOutlineStorageInterface $hierarchy_outline_storage) {
    $this->entityManager = $entity_manager;
    $this->stringTranslation = $translation;
    $this->configFactory = $config_factory;
    $this->hierarchyOutlineStorage = $hierarchy_outline_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function addHierarchyFormElement(array $form, FormStateInterface $form_state, NodeInterface $node, AccountInterface $account, $collapsed = TRUE) {
    $access = $account->hasPermission('administer hierarchy');
    $form['hierarchy'] =
      array(
        '#type' => 'details',
        '#title' => t('Page Hierarchy'),
        '#group' => 'advanced',
        '#open' => !$collapsed, //empty($form_state['nodehierarchy_expanded']) ? TRUE : FALSE,
        '#weight' => 10,
        '#access' => $access,
        '#tree' => FALSE,
      );
    $form['hierarchy']['nodehierarchy_parents'] = array('#tree' => TRUE);

    foreach ((array)$node->nodehierarchy_parents as $key => $parent) {
      $form['hierarchy']['nodehierarchy_parents'][$key] = $this->hierarchyNodeParentFormItems($node, $parent, $key);
      // Todo: determine if still needed/functionality
      \Drupal::moduleHandler()->alter('nodehierarchy_node_parent_form_items', $form['hierarchy']['nodehierarchy_parents'][$key], $node, $parent);
    }
    \Drupal::moduleHandler()->alter('nodehierarchy_node_parent_form_items_wrapper', $form['hierarchy'], $form_state, $node);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function hierarchyGetNodeTypeSettingsForm($key, $append_key = FALSE) {
    $config =  \Drupal::config('nodehierarchy.settings');

    $form['nh_allowchild'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Allowed child node types'),
      '#options' => node_type_get_names(),
      '#default_value' => $config->get('nh_allowchild_'.$key),
      '#description' => t('Node types which can be created as child nodes of this node type.'),
    );

    //$form['nh_defaultparent'] = _nodehierarchy_get_parent_selector($key, $config->get('nh_defaultparent_'.$key));
    // TODO: add default parent support later
    //$form['nh_defaultparent']['#title'] = t('Default Parent');

    // Todo: find out why this was removed in 7.x-4.x, and possibly remove from here and the Admin form.
    $form['nh_createmenu'] = array(
      '#type' => 'radios',
      '#title' => t('Show item in menu'),
      '#default_value' => $config->get('nh_createmenu_'.$key), //variable_get('nh_createmenu_' . $key, 'optional_no'),
      '#options' => array(
        'never' => t('Never'),
        'optional_no' => t('Optional - default to no'),
        'optional_yes' => t('Optional - default to yes'),
        'always' => t('Always'),
      ),
      '#description' => t("Users must have the 'administer menu' or 'customize nodehierarchy menus' permission to override default options."),
    );
    $form['nh_multiple'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow multiple parents'),
      '#default_value' => $config->get('nh_multiple_'.$key),
      '#description' => t('Can nodes of this type have multiple parents?.'),
    );

    $form['nh_defaultparent'] = $this->hierarchyGetParentSelector($key, $config->get('nh_defaultparent_' .$key, 0));
    $form['nh_defaultparent']['#title'] = t('Default Parent');

    $form += \Drupal::moduleHandler()->invokeAll('nodehierarchy_node_type_settings_form', array($key));

    // If we need to append the node type key to the form elements, we do so.
    if ($append_key) {
      // Appending the key does not work recursively, so fieldsets etc. are not supported.
      $children = \Drupal\Core\Render\Element::children($form);
      foreach ($children as $form_key) {
        $form[$form_key . '_' . $key] = $form[$form_key];
        unset($form[$form_key]);
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function hierarchyDefaultRecord($cnid = NULL, $pnid = NULL) {
    return (object)array(
      'pnid' => $pnid,
      // Todo: should this be cweight???
      'weight' => 0,
      'cnid' => $cnid,
    );
  }

  /**
   * {@inheritdoc}
   *
   * @see hierarchyGetAllowedParentTypes
   */
  public function hierarchyCanBeChild(NodeInterface $node) {
    $type = is_object($node) ? $node->getType() : $node;
    return count($this->hierarchyGetAllowedParentTypes($type));
  }

  /**
   * {@inheritdoc}
   *
   * @see hierarchyGetAllowedChildTypes
   */
  public function hierarchyCanBeParent(NodeInterface $node) {
    $type = is_object($node) ? $node->getType() : $node;
    return count($this->hierarchyGetAllowedChildTypes($type));
  }

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
   *
   * @see hierarchyCanBeChild
   * @see hierarchyParentOptions
   */
  private function hierarchyGetAllowedParentTypes($child_type = NULL) {
    // Static cache the results because this may be called many times for the same type on the menu overview screen.
    static $allowed_types = array();
    $config =  \Drupal::config('nodehierarchy.settings');

    if (!isset($allowed_types[$child_type])) {
      $parent_types = array();
      $types = \Drupal\node\Entity\NodeType::loadMultiple();
      foreach ($types as $type => $info) {
        $allowed_children_unfiltered = $config->get('nh_allowchild_' . $type);
        if ($allowed_children_unfiltered) {
          $allowed_children = array_filter($allowed_children_unfiltered);
        }
        if ((empty($child_type) && !empty($allowed_children)) || (in_array($child_type, (array) $allowed_children, TRUE))) {
          $parent_types[] = $type;
        }
      }
      $allowed_types[$child_type] = array_unique($parent_types);
    }

    return $allowed_types[$child_type];
  }

  /**
   * {@inheritdoc}
   */
  public function hierarchyGetAllowedChildTypes($parent_type) {
    $config =  \Drupal::config('nodehierarchy.settings');
    $child_types = array_filter($config->get('nh_allowchild_'.$parent_type));
    return array_unique($child_types);
  }

  /**
   * Build the parent form item for a given parent node object. This function is
   * called iteratively in addHierarchyFormElement method to build a dropdown
   * list of parents. We add a visible title (node title), the hierarchy id
   * (hid), a parent node id (pid), a child weight (cweight), a parent weight
   * (pweight), and a delete flag to each dropdown item.
   *
   * The dropdown element  to select the desired title is added by the method
   * hierarchyGetParentSelector.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being built or being edited
   * @param $parent
   *   The parent object
   * @return array
   *   The array containg all the important information associated with the
   *   given dropdown item.
   *
   * @see addHierarchyFormElement
   * @see hierarchyGetParentSelector
   */
  private function hierarchyNodeParentFormItems(NodeInterface $node, $parent) {
    // Wrap the item in a div for js purposes
    // Todo: change the dropdown selector to an autocomplete if it makes sense.
    $item = array(
      '#type' => 'fieldset',
      '#title' => t('Parent'),
      '#tree' => TRUE,
      '#prefix' => '<div class="nodehierarchy-parent">',
      '#suffix' => '</div>',
    );

    // The parent node ID
    $pnid = $parent->pnid;
    // The node ID of the node being created or edited
    $nid = $node->id();

    // If a node can be a child of another add a selector to pick the parent. Otherwise set the parent to 0.
    if ($this->hierarchyCanBeChild($node)) {
      $item['pnid'] = $this->hierarchyGetParentSelector($node->getType(), empty($pnid) ? null : $pnid, empty($nid) ? null : $nid);
      $item['pnid']['#weight'] = -1;
    }
    else {
      $item['pnid'] = array(
        '#type' => 'value',
        '#value' => 0,
      );
    }

    $item['hid'] = array(
      '#type' => 'value',
      '#value' => isset($parent->hid) ? $parent->hid : NULL,
    );
    $item['cweight'] = array(
      '#type' => 'value',
      '#value' => isset($parent->cweight) ? $parent->cweight : NULL,
    );
    $item['pweight'] = array(
      '#type' => 'value',
      '#value' => isset($parent->pweight) ? $parent->pweight : NULL,
    );

    if (!empty($parent->hid)) {
      $item['remove'] = array(
        '#type' => 'checkbox',
        '#title' => t('Remove this parent'),
        '#weight' => 100,
      );
    }

    return $item;
  }

  /**
   * Build a list of parent tiles to be displayed as part of a dropdown selector
   * in hierarchyNodeParentFormItems. First we grab a list of allowed parenta
   * using the hierarchyParentOptions method. Then we format each title by
   * iteratively calling hierarchyParentOptionTitle. Finally we build the actual
   * form element to be supplied to hierarchyNodeParentFormItems.
   *
   * @param string $child_type
   *   The child node type.
   * @param $parent
   *   The parent item being set on the form.
   * @param int/null $exclude
   *   Node ID of the parent that should be excluded from the list.
   * @return array
   *   The form array to be consumed by hierarchyNodeParentFormItems
   *
   * @see hierarchyNodeParentFormItems
   * @see hierarchyParentOptions
   * @see hierarchyParentOptionTitle
   */
  private function hierarchyGetParentSelector($child_type, $parent, $exclude = NULL) {
    // Allow other modules to create the pulldown first.
    // Modules implementing this hook, should return the form element inside an array with a numeric index.
    // This prevents module_invoke_all from merging the outputs to make an invalid form array.
    $out = \Drupal::moduleHandler()->invokeAll('nodehierarchy_get_parent_selector', array($child_type, $parent, $exclude));

    if ($out) {
      // Return the last element defined (any others are thrown away);
      return end($out);
    }

    $default_value = $parent;

    // If no other modules defined the pulldown, then define it here.
    $options = array(0 => '-- ' . t('NONE') . ' --');
    $items = $this->hierarchyParentOptions($child_type, $exclude);

    foreach ($items as $key => $item) {
      if(is_object($item))  {
        $options[$key] = $this->hierarchyParentOptionTitle($item);
      }
    }

    // Make sure the current value is enabled so items can be re-saved.
    if ($default_value && isset($items[$default_value])) {
      $items[$default_value]->disabled = 0;
    }

    $out = array(
      '#type' => 'select',
      '#title' => t('Parent Node'),
      '#default_value' => $default_value,
      '#attributes' => array('class' => array('nodehierarchy-parent-selector')),
      '#options' => $options,
      '#items' => $items,
      //'#theme' => 'nodehierarchy_parent_selector',
      //'#element_validate' => array('nodehierarchy_parent_selector_validate'),
    );
    return $out;
  }

  /**
   * Return a list of valid possible hierarchy parents for the given child node
   * type. This list is passed back to hierarchyGetParentSelector so it can be
   * displayed as a dropdown selection list.
   *
   * The list is built with the following steps:
   * 1) Get all allowed parent node types for all allowed node types using
   *    hierarchyGetAllowedParentTypes
   * 2) Get allowed parent node types for a given child node type using
   *    hierarchyGetAllowedParentTypes
   * 3) Build a tree of all possible parent nodes using hierarchyBuildTree and
   *    HierarchyOutlineStorage::hierarchyNodesByType
   * 4) Remove or disable parent titles that can't be parents using
   *    hierarchyTreeDisableTypes
   * 5) Remove items which the user does not have permission to access using
   *    hierarchyTreeDisableNoAccess
   * 6) Remove the option to set a child as its own parent using
   *    hierarchyTreeRemoveNid
   * 7) Convert the tree to a flattened list (with depth) using
   *    hierarchyFlattenTree
   *
   * @param string $child_type
   *   The node type of the child used to find valid potential parents
   * @param int/null $exclude
   *   Node ID of the parent that should be excluded from the list.
   * @return array
   *   The list of valid parents for a given node type.
   *
   * @see hierarchyGetParentSelector
   * @see hierarchyGetAllowedParentTypes
   * @see HierarchyOutlineStorage::hierarchyNodesByType
   * @see hierarchyBuildTree
   * @see hierarchyTreeDisableTypes
   * @see hierarchyTreeDisableNoAccess
   * @see hierarchyTreeRemoveNid
   * @see hierarchyFlattenTree
   */
  private function hierarchyParentOptions($child_type, $exclude = NULL) {
    static $options = array();

    // If these options have already been generated, then return that saved version.
    if (isset($options[$child_type][$exclude])) {
      return $options[$child_type][$exclude];
    }

    $types = $this->hierarchyGetAllowedParentTypes();
    $parent_types = $this->hierarchyGetAllowedParentTypes($child_type);
    $parent_tree = $this->hierarchyOutlineStorage->hierarchyNodesByType($types);
    $parent_tree = $this->hierarchyBuildTree($parent_tree);
    $parent_tree = $this->hierarchyTreeDisableTypes($parent_tree, $parent_types);
//    $parent_tree = $this->hierarchyTreeDisableNoAccess($parent_tree);
    $out = $this->hierarchyTreeRemoveNid($parent_tree, $exclude);
    $out = $this->hierarchyFlattenTree($out);
    // Apply static caching to prevent these options being built more than once.
    $options[$child_type][$exclude] = $out;

    return $out;
  }

  /**
   * Format the title of a given item to display in a pulldown.
   *
   * @param object $item
   *   The title to be formatted
   * @return string
   *   The formatted title
   *
   * @see hierarchyGetParentSelector
   */
  private function hierarchyParentOptionTitle($item) {
    return str_repeat('--', $item->depth - 1) . ' ' . Unicode::truncate($item->title, 45, TRUE, FALSE);
  }
  
  /**
   *  Build a tree of all possible parent nodes. This method is only called for
   *  a given node type.
   *
   * @param $nodes
   *   The list of nodes to process
   * @return object
   *   The updated tree of nodes
   *
   * @see hierarchyParentOptions
   */
  private function hierarchyBuildTree($nodes) {
    foreach ($nodes as $node) {
      $node->is_child = FALSE;
      $node->disabled = FALSE;
      if (!empty($node->pnid)) {
        if(isset($nodes[$node->pnid])) {
          $node->is_child = TRUE;
          $nodes[$node->pnid]->children[$node->nid] = &$nodes[$node->nid];
        }
      }
    }
    foreach ($nodes as $nid => $node) {
      if ($node->is_child) {
        unset($nodes[$nid]);
      }
    }
    return $nodes;
  }

  /**
   * Recursively mark nodes that are not of the given types as disabled.
   *
   * @param $nodes
   *   The list of nodes to process and mark as disabled where applicable.
   * @param array $allowed_types
   *    The list of allowed child types
   * @return object $nodes
   *    The updated tree of nodes with appropriate nodes marked as disabled.
   *
   * @see hierarchyParentOptions
   */
  private function hierarchyTreeDisableTypes($nodes, $allowed_types) {
    foreach ($nodes as $nid => $node) {
      if (!in_array($node->type, $allowed_types)) {
        $nodes[$nid]->disabled = TRUE;
      }
      if (!empty($node->children)) {
        $nodes[$nid]->children = $this->hierarchyTreeDisableTypes($node->children, $allowed_types);
      }
    }
    return $nodes;
  }

  /**
   * Recursively mark as disabled nodes for which the user doesn't have edit
   * access.
   * 
   * @param $nodes
   *   The list of nodes to process for access permissions
   * @return object
   *   The updated list of nodes with no access nodes marked as disabled.
   * 
   * @see hierarchyParentOptions
   */
  private function hierarchyTreeDisableNoAccess(NodeInterface $nodes) {
    // Todo: fix this function 
    $current_user = \Drupal::currentUser();
    if (!$current_user->hasPermission('create child of any parent')) {
      foreach ($nodes as $nid => $node) {
        $nodes[$nid]->disabled = $nodes[$nid]->disabled || !$node->access('update');
        if (!empty($node->children)) {
          $nodes[$nid]->children = $this->hierarchyTreeDisableNoAccess($node->children);
        }
      }
    }
    return $nodes;
  }

  /**
   * Remove the option to set a child as its own parent. All decedents of the
   * parent are also recursively removed.
   *
   * @param $nodes
   *   The list of nodes to process
   * @param $exclude
   *   Node ID of the parent that should be excluded from the list.
   * @return object
   *   The updated tree
   *
   * @see hierarchyParentOptions
   */
  private function hierarchyTreeRemoveNid($nodes, $exclude) {
    foreach ($nodes as $nid => $node) {
      if ($nid == $exclude) {
        unset($nodes[$nid]);
      }
      else if (!empty($node->children)) {
        $nodes[$nid]->children = $this->hierarchyTreeRemoveNid($node->children, $exclude);
      }
    }
    return $nodes;
  }

  /**
   * Recursively convert the tree to a flattened list (with depth)
   *
   * @param $nodes
   *   The list of nodes to process
   * @param int $depth
   *   The depth of the list
   * @return array
   *   The flattened list to be used in the dropdown selector of available
   *   parent nodes.
   *
   * @see hierarchyParentOptions
   */
  private function hierarchyFlattenTree($nodes, $depth = 1) {
    $out = $children = array();
    foreach ($nodes as $nid => $node) {
      $node->depth = $depth;
      $children = array();
      if (!empty($node->children)) {
        $children = $this->hierarchyFlattenTree($node->children, $depth + 1);
      }
      // Only output this option if there are non-disabled children.
      if (!$node->disabled || $children) {
        $out[$nid] = $node;
        $out += $children;
      }
    }
    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function hierarchySaveNode(&$node) {
    if (!isset($node->nodehierarchy_parents)) {
      return;
    }
    foreach ($node->nodehierarchy_parents as $i => $item) {
      $node->nodehierarchy_parents[$i] = (object)$item;
      $node->nodehierarchy_parents[$i]->cnid = (int)$node->id();
      if (!empty($node->nodehierarchy_parents[$i]->remove)) {
        $node->nodehierarchy_parents[$i]->pnid = NULL;
      }
      $this->hierarchyRecordSave($node->nodehierarchy_parents[$i]);
    }
  }

  /**
   * Prepare an individual item to be added/removed to the database.
   *
   * If the item already has a hierarchy id (hid) do the following:
   * 1) If the item doesn't have a parent node id (pnid), it is deleted via
   *    hierarchyDeleteRecord. Otherwise, load the current item from the
   *    database using hierarchyGetRecord
   * 2) Adjust the child weight (cweight) via hierarchyGetParentNextChildWeight.
   * 3) Update the database with the new weight using updateHierarchy.
   *
   * If the item doesn't already have an hid:
   * 1) Load the next available cweight using hierarchyGetParentNextChildWeight.
   * 2) Insert a new hierarchy record into the database via insertHierarchy.
   *
   * @param object $item
   *   The hierarchy object to be processed before adding the item to the
   *   database, updating an existing item, or deleting the item from the
   *   database.
   *
   * @see HierarchyManagerInterface::hierarchySaveNode
   * @see hierarchyDeleteRecord
   * @see hierarchyGetRecord
   * @see hierarchyGetParentNextChildWeight
   * @see insertHierarchy
   * @see updateHierarchy
   */
  private function hierarchyRecordSave(&$item) {
    if (!empty($item->hid)) {
      // Remove the item if it's no longer needed.
      if (empty($item->pnid)) {
        $this->hierarchyDeleteRecord($item->hid);
      }
      else {
        $existing_item = $this->hierarchyGetRecord($item->hid);
        // If the parent has been changed:
        if ($existing_item->pnid !== $item->pnid) {
          $item->cweight = $this->hierarchyGetParentNextChildWeight($item->pnid);
        }
      }
    }
    else {
      $item->cweight = $this->hierarchyGetParentNextChildWeight($item->pnid);
    }
    if ($item->pnid) {
      if (empty($item->hid)) {
        $this->insertHierarchy($item);
      }
      else {
        $this->updateHierarchy($item);
      }
    }
  }

  /**
   * Add a new hierarchy record to the database using the
   * HierarchyOutlineStorage class.
   *
   * @param object $item
   *   The hierarchy object to be written to the database.
   * @return mixed
   *   Todo: figure out what's being returned
   *
   * @see HierarchyOutlineStorage::insert
   * @see hierarchyRecordSave
   */
  private function insertHierarchy($item){
    return $this->hierarchyOutlineStorage->insert($item);
  }

  /**
   * {@inheritdoc}
   */
  public function updateHierarchy($item){
    unset($item->remove);
    // Here we typecast the $item object to an array, and PHP is smart enough to convert it.
    return $this->hierarchyOutlineStorage->update($item->hid, (array)$item);
  }

  /**
   * Deletes an existing hierarchy item from the database using the
   * HierarchyOutlineStorage class.
   *
   * @param int $hid
   *   The hierarchy id to be deleted from the database.
   * @return mixed
   *   Todo: figure out what's being returned
   *
   * @see HierarchyOutlineStorage::hierarchyRecordDelete
   * @see hierarchyRecordSave
   */
  private function hierarchyDeleteRecord($hid) {
    return $this->hierarchyOutlineStorage->hierarchyRecordDelete($hid);
  }

  /**
   * Loads a single hierarchy item from the database using the
   * HierarchyOutlineStorage class.
   *
   * @param int $hid
   *   The hierarchy id to be loaded from the database.
   * @return mixed
   *   Todo: figure out what's being returned
   *
   * @see HierarchyOutlineStorage::hierarchyRecordLoad
   * @see hierarchyRecordSave
   */
  private function hierarchyGetRecord($hid){
    return $this->hierarchyOutlineStorage->hierarchyRecordLoad($hid);
  }

  /**
   * Query the database to find the next available child weight for the given
   * parent using the HierarchyOutlineStorage class.
   *
   * @param int $pnid
   *   The parent id used to query the database to find the next available child
   *   weight where applicable.
   * @return mixed
   *   Todo: figure out what's being returned
   *
   * @see HierarchyOutlineStorage::hierarchyLoadParentNextChildWeight
   * @see hierarchyRecordSave
   */
  private function hierarchyGetParentNextChildWeight($pnid) {
    return $this->hierarchyOutlineStorage->hierarchyLoadParentNextChildWeight($pnid);
  }

}
