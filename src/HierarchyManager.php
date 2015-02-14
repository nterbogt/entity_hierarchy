<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\HierarchyManager.
 */

namespace Drupal\nodehierarchy;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityManagerInterface;
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
   * @var \Drupal\Core\Entity\EntityManagerInterface
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
   */
  public function __construct(EntityManagerInterface $entity_manager, TranslationInterface $translation, ConfigFactoryInterface $config_factory, HierarchyOutlineStorageInterface $hierarchy_outline_storage) {
    $this->entityManager = $entity_manager;
    $this->stringTranslation = $translation;
    $this->configFactory = $config_factory;
    $this->hierarchyOutlineStorage = $hierarchy_outline_storage;
  }

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
  public function hierarchyCanBeChild(NodeInterface $node) {
    $type = is_object($node) ? $node->getType() : $node;
    return count($this->hierarchyGetAllowedParentTypes($type));
  }

  /**
   * {@inheritdoc}
   */
  public function hierarchyCanBeParent(NodeInterface $node) {
    $type = is_object($node) ? $node->getType() : $node;
    return count($this->hierarchyGetAllowedChildTypes($type));
  }

  /**
   * Get the parent and menu setting for items for a given parent menu_link.
   *
   * {@inheritdoc}
   */
  private function hierarchyNodeParentFormItems(NodeInterface $node, $parent) {
    // Wrap the item in a div for js purposes
    $item = array(
      '#type' => 'fieldset',
      '#title' => t('Parent'),
      '#tree' => TRUE,
      '#prefix' => '<div class="nodehierarchy-parent">',
      '#suffix' => '</div>',
    );

    $pnid = $parent->pnid;
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
   * Get the allowed parent types for the given child type.
   */
  public function hierarchyGetAllowedParentTypes($child_type = NULL) {
    // Static cache the results because this may be called many times for the same type on the menu overview screen.
    static $allowed_types = array();
    $config =  \Drupal::config('nodehierarchy.settings');

    if (!isset($allowed_types[$child_type])) {
      $parent_types = array();
      $types = \Drupal\node\Entity\NodeType::loadMultiple();
      foreach ($types as $type => $info) {

        $allowed_children = array_filter($config->get('nh_allowchild_' . $type, array()));
        if ((empty($child_type) && !empty($allowed_children)) || (in_array($child_type, (array) $allowed_children, TRUE))) {
          $parent_types[] = $type;
        }
      }
      $allowed_types[$child_type] = array_unique($parent_types);
    }
    return $allowed_types[$child_type];
  }

  /**
   * Get the allowed child types for the given parent.
   */
  public function hierarchyGetAllowedChildTypes($parent_type) {
    // TODO: is this function still required? Any reason to think we may need array_filter($config->get...)?
    $config =  \Drupal::config('nodehierarchy.settings');
    $child_types = array_filter($config->get('nh_allowchild_'.$parent_type));
    return array_unique($child_types);
  }

  /**
   * Get the parent selector pulldown.
   */
  public function hierarchyGetParentSelector($child_type, $parent, $exclude = NULL) {
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
   * Get the title of the given item to display in a pulldown.
   */
  private function hierarchyParentOptionTitle($item) {
    return str_repeat('--', $item->depth - 1) . ' ' . Unicode::truncate($item->title, 45, TRUE, FALSE);
  }


  /**
   * Return a list of menu items that are valid possible parents for the given node.
   */
  private function hierarchyParentOptions($child_type, $exclude = NULL) {
    static $options = array();

    // If these options have already been generated, then return that saved version.
    if (isset($options[$child_type][$exclude])) {
      return $options[$child_type][$exclude];
    }

    $types = $this->hierarchyGetAllowedParentTypes();
    $parent_types = $this->hierarchyGetAllowedParentTypes($child_type);

    // Build the whole tree.
    $parent_tree = $this->hierarchyOutlineStorage->hierarchyNodesByType($types);
    $parent_tree = $this->hierarchyBuildTree($parent_tree);

    // Remove or disable items that can't be parents.
    $parent_tree = $this->hierarchyTreeDisableTypes($parent_tree, $parent_types);

    // Remove items which the user does not have permission to.
    $parent_tree = $this->hierarchyTreeDisableNoaccess($parent_tree);

    // Remove the excluded item(s). This prevents a child being assigned as it's own parent.
    $out = $this->hierarchyTreeRemoveNid($parent_tree, $exclude);

    // Convert the tree to a flattened list (with depth)
    $out = $this->hierarchyFlattenTree($out);

    // Static caching to prevent these options being built more than once.
    $options[$child_type][$exclude] = $out;

    return $out;
  }

  /**
   * Mark nodes which the user does not have edit access to as disabled.
   */
  private function hierarchyTreeDisableNoaccess(NodeInterface $nodes) {
    $current_user = \Drupal::currentUser();
    if (!$current_user->hasPermission('create child of any parent')) {
      foreach ($nodes as $nid => $node) {
        $nodes[$nid]->disabled = $nodes[$nid]->disabled || !$node->access('update');

        if (!empty($node->children)) {
          $nodes[$nid]->children = $this->hierarchyTreeDisableNoaccess($node->children);
        }
      }
    }
    return $nodes;
  }

  /**
   * Get a tree of nodes of the given type.
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
   * Mark nodes that are not of the given types as disabled.
   */
  function hierarchyTreeDisableTypes($nodes, $allowed_types) {
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
   * Mark nodes with the given node and it's decendents as disabled.
   */
  function hierarchyTreeRemoveNid($nodes, $exclude) {
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
   * Flatten the tree of nodes.
   */
  function hierarchyFlattenTree($nodes, $depth = 1) {
    $out = $children = array();
    foreach ($nodes as $nid => $node) {
      $node->depth = $depth;
      $children = array();
      if (!empty($node->children)) {
        $children = $this->hierarchyFlattenTree($node->children, $depth + 1);
      }

      // Only output this option if there are non-disabled chidren.
      if (!$node->disabled || $children) {
        $out[$nid] = $node;
        $out += $children;
      }
    }
    return $out;
  }

  /**
   * Do the actual insertion or update. No permissions checking is done here.
   */
  function hierarchySaveNode(&$node) {
    if (!isset($node->nodehierarchy_parents)) {
      return;
    }

//    dsm($node->nodehierarchy_parents);

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
   * Save a nodehierarchy record, resetting weights if applicable
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

  public function insertHierarchy($item){
    return $this->hierarchyOutlineStorage->insert($item);
  }

  public function updateHierarchy($item){
    return $this->hierarchyOutlineStorage->update($item->hid, $item);
  }

  /**
   * Get the next child weight for a given pnid.
   */
  public function hierarchyGetParentNextChildWeight($pnid) {
    return $this->hierarchyOutlineStorage->hierarchyLoadParentNextChildWeight($pnid);
  }

  public function hierarchyGetRecord($hid){
    return $this->hierarchyOutlineStorage->hierarchyRecordLoad($hid);
  }

  /**
   * Save a nodehierarchy record.
   */
  public function hierarchyDeleteRecord($hid) {
    return $this->hierarchyOutlineStorage->hierarchyRecordDelete($hid);
  }

  /**
   * Get the nodehierarchy setting form for a particular node type.
   */
  function hierarchyGetNodeTypeSettingsForm($key, $append_key = FALSE) {
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

    //$form += module_invoke_all('nodehierarchy_node_type_settings_form', $key);

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
   * Get the default menu link values for a new nodehierarchy menu link.
   */
  public function hierarchyDefaultRecord($cnid = NULL, $npid = NULL) {
    return (object)array(
      'pnid' => $npid,
      'weight' => 0,
      'cnid' => $cnid,
    );
  }

  public function loadHierarchy($nids) {
    return $this->hierarchyOutlineStorage->loadHierarchies($nids);
  }

}
