<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\HierarchyManager.
 */

namespace Drupal\nodehierarchy;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\Cache;
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

  /**
   * {@inheritdoc}
   */
  public function getAllHierarchies() {
    if (!isset($this->hierarchies)) {
      $this->loadHierarchies();
    }
    return $this->hierarchies;
  }

  /**
   * Loads Hierarchies Array.
   */
  protected function loadHierarchies() {
    $this->hierarchies = array();
    $nids = $this->hierarchyOutlineStorage->getHierarchies();

    if ($nids) {
      $hierarchy_links = $this->hierarchyOutlineStorage->loadMultiple($nids);
      $nodes = $this->entityManager->getStorage('node')->loadMultiple($nids);
      // @todo: Sort by weight and translated title.

      // @todo: use route name for links, not system path.
      foreach ($hierarchy_links as $link) {
        $nid = $link['nid'];
        if (isset($nodes[$nid]) && $nodes[$nid]->status) {
          $link['url'] = $nodes[$nid]->urlInfo();
          $link['title'] = $nodes[$nid]->label();
          $link['type'] = $nodes[$nid]->bundle();
          $this->hierarchies[$link['hid']] = $link;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLinkDefaults($nid) {
    return array(
      'original_hid' => 0,
      'nid' => $nid,
      'hid' => 0,
      'pid' => 0,
      'has_children' => 0,
      'weight' => 0,
      'options' => array(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getParentDepthLimit(array $hierarchy_link) {
    return static::HIERARCHY_MAX_DEPTH - 1 - (($hierarchy_link['hid'] && $hierarchy_link['has_children']) ? $this->findChildrenRelativeDepth($hierarchy_link) : 0);
  }

  /**
   * Determine the relative depth of the children of a given hierarchy link.
   *
   * @param array
   *   The hierarchy link.
   *
   * @return int
   *   The difference between the max depth in the hierarchy tree and the depth of
   *   the passed hierarchy link.
   */
  protected function findChildrenRelativeDepth(array $hierarchy_link) {
    $max_depth = $this->hierarchyOutlineStorage->getChildRelativeDepth($hierarchy_link, static::HIERARCHY_MAX_DEPTH);
    return ($max_depth > $hierarchy_link['depth']) ? $max_depth - $hierarchy_link['depth'] : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function addFormElements(array $form, FormStateInterface $form_state, NodeInterface $node, AccountInterface $account, $collapsed = TRUE) {
    // If the form is being processed during the Ajax callback of our hierarchy hid
    // dropdown, then $form_state will hold the value that was selected.
    if ($form_state->hasValue('hierarchy')) {
      $node->hierarchy = $form_state->getValue('hierarchy');
    }
    $form['old-hierarchy'] = array(
      '#type' => 'details',
      '#title' => $this->t('Set Hierarchy Page'),
      '#weight' => 10,
      '#open' => !$collapsed,
      '#group' => 'advanced',
      '#attributes' => array(
        'class' => array('hierarchy-outline-form'),
      ),
      '#attached' => array(
        'library' => array('hierarchy/drupal.hierarchy'),
      ),
      '#tree' => TRUE,
    );
    foreach (array('nid', 'has_children', 'original_hid', 'parent_depth_limit') as $key) {
      $form['old-hierarchy'][$key] = array(
        '#type' => 'value',
        '#value' => $node->hierarchy[$key],
      );
    }

    $form['old-hierarchy']['pid'] = $this->addParentSelectFormElements($node->hierarchy);

    // @see \Drupal\hierarchy\Form\HierarchyAdminEditForm::hierarchyAdminTableTree(). The
    // weight may be larger than 15.
    $form['old-hierarchy']['weight'] = array(
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#default_value' => $node->hierarchy['weight'],
      '#delta' => max(15, abs($node->hierarchy['weight'])),
      '#weight' => 5,
      '#description' => $this->t('Pages at a given level are ordered first by weight and then by title.'),
    );
    $options = array();
    $nid = !$node->isNew() ? $node->id() : 'new';
    if ($node->id() && ($nid == $node->hierarchy['original_hid']) && ($node->hierarchy['parent_depth_limit'] == 0)) {
      // This is the top level node in a maximum depth hierarchy and thus cannot be moved.
      $options[$node->id()] = $node->label();
    }
    else {
      foreach ($this->getAllHierarchies() as $hierarchy) {
        $options[$hierarchy['nid']] = $hierarchy['title'];
      }
    }

    if ($account->hasPermission('create new hierarchies') && ($nid == 'new' || ($nid != $node->hierarchy['original_hid']))) {
      // The node can become a new hierarchy, if it is not one already.
      $options = array($nid => $this->t('- Create a new hierarchy -')) + $options;
    }
    if (!$node->hierarchy['hid']) {
      // The node is not currently in the hierarchy.
      $options = array(0 => $this->t('- None -')) + $options;
    }

    // Add a drop-down to select the destination hierarchy.
    $form['old-hierarchy']['hid'] = array(
      '#type' => 'select',
      '#title' => $this->t('Hierarchy'),
      '#default_value' => $node->hierarchy['hid'],
      '#options' => $options,
      '#access' => (bool) $options,
      '#description' => $this->t('Your page will be a part of the selected hierarchy.'),
      '#weight' => -5,
      '#attributes' => array('class' => array('hierarchy-title-select')),
      '#ajax' => array(
        'callback' => 'hierarchy_form_update',
        'wrapper' => 'edit-hierarchy-plid-wrapper',
        'effect' => 'fade',
        'speed' => 'fast',
      ),
    );
    return $form;
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
    $form['nodehierarchy']['nodehierarchy_parents'] = array('#tree' => TRUE);

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

    $item['nhid'] = array(
      '#type' => 'value',
      '#value' => isset($parent->nhid) ? $parent->nhid : NULL,
    );
    $item['cweight'] = array(
      '#type' => 'value',
      '#value' => isset($parent->cweight) ? $parent->cweight : NULL,
    );
    $item['pweight'] = array(
      '#type' => 'value',
      '#value' => isset($parent->pweight) ? $parent->pweight : NULL,
    );

    if (!empty($parent->nhid)) {
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
      //'#items' => $items,
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
   * {@inheritdoc}
   */
  public function checkNodeIsRemovable(NodeInterface $node) {
    return (!empty($node->hierarchy['hid']) && (($node->hierarchy['hid'] != $node->id()) || !$node->hierarchy['has_children']));
  }

  /**
   * {@inheritdoc}
   */
  public function updateOutline(NodeInterface $node) {
    if (empty($node->hierarchy['hid'])) {
      return FALSE;
    }

    if (!empty($node->hierarchy['hid'])) {
      if ($node->hierarchy['hid'] == 'new') {
        // New nodes that are their own hierarchy.
        $node->hierarchy['hid'] = $node->id();
      }
      elseif (!isset($node->hierarchy['original_hid'])) {
        $node->hierarchy['original_hid'] = $node->hierarchy['hid'];
      }
    }

    // Ensure we create a new hierarchy link if either the node itself is new, or the
    // hid was selected the first time, so that the original_hid is still empty.
    $new = empty($node->hierarchy['nid']) || empty($node->hierarchy['original_hid']);

    $node->hierarchy['nid'] = $node->id();

    // Create a new hierarchy from a node.
    if ($node->hierarchy['hid'] == $node->id()) {
      $node->hierarchy['pid'] = 0;
    }
    elseif ($node->hierarchy['pid'] < 0) {
      // -1 is the default value in HierarchyManager::addParentSelectFormElements().
      // The node save should have set the hid equal to the node ID, but
      // handle it here if it did not.
      $node->hierarchy['pid'] = $node->hierarchy['hid'];
    }
    return $this->saveHierarchyLink($node->hierarchy, $new);
  }

  /**
   * {@inheritdoc}
   */
  public function getHierarchyParents(array $item, array $parent = array()) {
    $hierarchy = array();
    if ($item['pid'] == 0) {
      $hierarchy['p1'] = $item['nid'];
      for ($i = 2; $i <= static::HIERARCHY_MAX_DEPTH; $i++) {
        $parent_property = "p$i";
        $hierarchy[$parent_property] = 0;
      }
      $hierarchy['depth'] = 1;
    }
    else {
      $i = 1;
      $hierarchy['depth'] = $parent['depth'] + 1;
      while ($i < $hierarchy['depth']) {
        $p = 'p' . $i++;
        $hierarchy[$p] = $parent[$p];
      }
      $p = 'p' . $i++;
      // The parent (p1 - p9) corresponding to the depth always equals the nid.
      $hierarchy[$p] = $item['nid'];
      while ($i <= static::HIERARCHY_MAX_DEPTH) {
        $p = 'p' . $i++;
        $hierarchy[$p] = 0;
      }
    }
    return $hierarchy;
  }

  /**
   * Builds the parent selection form element for the node form or outline tab.
   *
   * This function is also called when generating a new set of options during the
   * Ajax callback, so an array is returned that can be used to replace an
   * existing form element.
   *
   * @param array $hierarchy_link
   *   A fully loaded hierarchy link that is part of the hierarchy hierarchy.
   *
   * @return array
   *   A parent selection form element.
   */
  protected function addParentSelectFormElements(array $hierarchy_link) {
    if ($this->configFactory->get('hierarchy.settings')->get('override_parent_selector')) {
      return array();
    }
    // Offer a message or a drop-down to choose a different parent page.
    $form = array(
      '#type' => 'hidden',
      '#value' => -1,
      '#prefix' => '<div id="edit-hierarchy-plid-wrapper">',
      '#suffix' => '</div>',
    );

    if ($hierarchy_link['nid'] === $hierarchy_link['hid']) {
      // This is a hierarchy - at the top level.
      if ($hierarchy_link['original_hid'] === $hierarchy_link['hid']) {
        $form['#prefix'] .= '<em>' . $this->t('This is the top-level page in this hierarchy.') . '</em>';
      }
      else {
        $form['#prefix'] .= '<em>' . $this->t('This will be the top-level page in this hierarchy.') . '</em>';
      }
    }
    elseif (!$hierarchy_link['hid']) {
      $form['#prefix'] .= '<em>' . $this->t('No hierarchy selected.') . '</em>';
    }
    else {
      $form = array(
        '#type' => 'select',
        '#title' => $this->t('Parent item'),
        '#default_value' => $hierarchy_link['pid'],
        '#description' => $this->t('The parent page in the hierarchy. The maximum depth for a hierarchy and all child pages is !maxdepth. Some pages in the selected hierarchy may not be available as parents if selecting them would exceed this limit.', array('!maxdepth' => static::HIERARCHY_MAX_DEPTH)),
        '#options' => $this->getTableOfContents($hierarchy_link['hid'], $hierarchy_link['parent_depth_limit'], array($hierarchy_link['nid'])),
        '#attributes' => array('class' => array('hierarchy-title-select')),
        '#prefix' => '<div id="edit-hierarchy-plid-wrapper">',
        '#suffix' => '</div>',
      );
    }

    return $form;
  }

  /**
   * Recursively processes and formats hierarchy links for getTableOfContents().
   *
   * This helper function recursively modifies the table of contents array for
   * each item in the hierarchy tree, ignoring items in the exclude array or at a depth
   * greater than the limit. Truncates titles over thirty characters and appends
   * an indentation string incremented by depth.
   *
   * @param array $tree
   *   The data structure of the hierarchy's outline tree. Includes hidden links.
   * @param string $indent
   *   A string appended to each node title. Increments by '--' per depth
   *   level.
   * @param array $toc
   *   Reference to the table of contents array. This is modified in place, so the
   *   function does not have a return value.
   * @param array $exclude
   *   Optional array of Node ID values. Any link whose node ID is in this
   *   array will be excluded (along with its children).
   * @param int $depth_limit
   *   Any link deeper than this value will be excluded (along with its children).
   */
  protected function recurseTableOfContents(array $tree, $indent, array &$toc, array $exclude, $depth_limit) {
    $nids = array();
    foreach ($tree as $data) {
      if ($data['link']['depth'] > $depth_limit) {
        // Don't iterate through any links on this level.
        break;
      }
      if (!in_array($data['link']['nid'], $exclude)) {
        $nids[] = $data['link']['nid'];
      }
    }

    $nodes = $this->entityManager->getStorage('node')->loadMultiple($nids);

    foreach ($tree as $data) {
      $nid = $data['link']['nid'];
      if (in_array($nid, $exclude)) {
        continue;
      }
      $toc[$nid] = $indent . ' ' . Unicode::truncate($nodes[$nid]->label(), 30, TRUE, TRUE);
      if ($data['below']) {
        $this->recurseTableOfContents($data['below'], $indent . '--', $toc, $exclude, $depth_limit);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTableOfContents($hid, $depth_limit, array $exclude = array()) {
    $tree = $this->hierarchyTreeAllData($hid);
    $toc = array();
    $this->recurseTableOfContents($tree, '', $toc, $exclude, $depth_limit);

    return $toc;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFromHierarchy($nid) {
    $original = $this->loadHierarchyLink($nid, FALSE);
    $this->hierarchyOutlineStorage->delete($nid);

    if ($nid == $original['hid']) {
      // Handle deletion of a top-level post.
      $result = $this->hierarchyOutlineStorage->loadHierarchyChildren($nid);

      foreach ($result as $child) {
        $child['hid'] = $child['nid'];
        $this->updateOutline($child);
      }
    }
    $this->updateOriginalParent($original);
    $this->hierarchies = NULL;
    \Drupal::cache('data')->deleteTags(array('hid:' . $original['hid']));
  }

  /**
   * {@inheritdoc}
   */
  public function hierarchyTreeAllData($hid, $link = NULL, $max_depth = NULL) {
    $tree = &drupal_static(__METHOD__, array());
    $language_interface = \Drupal::languageManager()->getCurrentLanguage();

    // Use $nid as a flag for whether the data being loaded is for the whole
    // tree.
    $nid = isset($link['nid']) ? $link['nid'] : 0;
    // Generate a cache ID (cid) specific for this $hid, $link, $language, and
    // depth.
    $cid = 'hierarchy-links:' . $hid . ':all:' . $nid . ':' . $language_interface->getId() . ':' . (int) $max_depth;

    if (!isset($tree[$cid])) {
      // If the tree data was not in the static cache, build $tree_parameters.
      $tree_parameters = array(
        'min_depth' => 1,
        'max_depth' => $max_depth,
      );
      if ($nid) {
        $active_trail = $this->getActiveTrailIds($hid, $link);
        $tree_parameters['expanded'] = $active_trail;
        $tree_parameters['active_trail'] = $active_trail;
        $tree_parameters['active_trail'][] = $nid;
      }

      // Build the tree using the parameters; the resulting tree will be cached.
      $tree[$cid] = $this->hierarchyTreeBuild($hid, $tree_parameters);
    }

    return $tree[$cid];
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveTrailIds($hid, $link) {
    $nid = isset($link['nid']) ? $link['nid'] : 0;
    // The tree is for a single item, so we need to match the values in its
    // p columns and 0 (the top level) with the plid values of other links.
    $active_trail = array(0);
    for ($i = 1; $i < static::HIERARCHY_MAX_DEPTH; $i++) {
      if (!empty($link["p$i"])) {
        $active_trail[] = $link["p$i"];
      }
    }
    return $active_trail;
  }

  /**
   * {@inheritdoc}
   */
  public function hierarchyTreeOutput(array $tree) {
    $build = array();
    $items = array();

    // Pull out just the hierarchy links we are going to render so that we
    // get an accurate count for the first/last classes.
    foreach ($tree as $data) {
      if ($data['link']['access']) {
        $items[] = $data;
      }
    }

    $num_items = count($items);
    foreach ($items as $i => $data) {
      $class = array();
      if ($i == 0) {
        $class[] = 'first';
      }
      if ($i == $num_items - 1) {
        $class[] = 'last';
      }
      // Set a class for the <li>-tag. Since $data['below'] may contain local
      // tasks, only set 'expanded' class if the link also has children within
      // the current hierarchy.
      if ($data['link']['has_children'] && $data['below']) {
        $class[] = 'expanded';
      }
      elseif ($data['link']['has_children']) {
        $class[] = 'collapsed';
      }
      else {
        $class[] = 'leaf';
      }
      // Set a class if the link is in the active trail.
      if ($data['link']['in_active_trail']) {
        $class[] = 'active-trail';
        $data['link']['localized_options']['attributes']['class'][] = 'active-trail';
      }

      // Allow hierarchy-specific theme overrides.
      $element['#theme'] = 'hierarchy_link__hierarchy_toc_' . $data['link']['hid'];
      $element['#attributes']['class'] = $class;
      $element['#title'] = $data['link']['title'];
      $node = $this->entityManager->getStorage('node')->load($data['link']['nid']);
      $element['#url'] = $node->urlInfo();
      $element['#localized_options'] = !empty($data['link']['localized_options']) ? $data['link']['localized_options'] : array();
      $element['#below'] = $data['below'] ? $this->hierarchyTreeOutput($data['below']) : $data['below'];
      $element['#original_link'] = $data['link'];
      // Index using the link's unique nid.
      $build[$data['link']['nid']] = $element;
    }
    if ($build) {
      // Make sure drupal_render() does not re-order the links.
      $build['#sorted'] = TRUE;
      // Add the theme wrapper for outer markup.
      // Allow hierarchy-specific theme overrides.
      $build['#theme_wrappers'][] = 'hierarchy_tree__hierarchy_toc_' . $data['link']['hid'];
    }

    return $build;
  }

  /**
   * Builds a hierarchy tree, translates links, and checks access.
   *
   * @param int $hid
   *   The Hierarchy ID to find links for.
   * @param array $parameters
   *   (optional) An associative array of build parameters. Possible keys:
   *   - expanded: An array of parent link ids to return only hierarchy links that are
   *     children of one of the plids in this list. If empty, the whole outline
   *     is built, unless 'only_active_trail' is TRUE.
   *   - active_trail: An array of nids, representing the coordinates of the
   *     currently active hierarchy link.
   *   - only_active_trail: Whether to only return links that are in the active
   *     trail. This option is ignored, if 'expanded' is non-empty.
   *   - min_depth: The minimum depth of hierarchy links in the resulting tree.
   *     Defaults to 1, which is the default to build a whole tree for a hierarchy.
   *   - max_depth: The maximum depth of hierarchy links in the resulting tree.
   *   - conditions: An associative array of custom database select query
   *     condition key/value pairs; see _menu_build_tree() for the actual query.
   *
   * @return array
   *   A fully built hierarchy tree.
   */
  protected function hierarchyTreeBuild($hid, array $parameters = array()) {
    // Build the hierarchy tree.
    $data = $this->doHierarchyTreeBuild($hid, $parameters);
    // Check access for the current user to each item in the tree.
    $this->hierarchyTreeCheckAccess($data['tree'], $data['node_links']);
    return $data['tree'];
  }

  /**
   * Builds a hierarchy tree.
   *
   * This function may be used build the data for a menu tree only, for example
   * to further massage the data manually before further processing happens.
   * _menu_tree_check_access() needs to be invoked afterwards.
   *
   * @see menu_build_tree()
   */
  protected function doHierarchyTreeBuild($hid, array $parameters = array()) {
    // Static cache of already built menu trees.
    $trees = &drupal_static(__METHOD__, array());
    $language_interface = \Drupal::languageManager()->getCurrentLanguage();

    // Build the cache id; sort parents to prevent duplicate storage and remove
    // default parameter values.
    if (isset($parameters['expanded'])) {
      sort($parameters['expanded']);
    }
    $tree_cid = 'hierarchy-links:' . $hid . ':tree-data:' . $language_interface->getId() . ':' . hash('sha256', serialize($parameters));

    // If we do not have this tree in the static cache, check {cache_data}.
    if (!isset($trees[$tree_cid])) {
      $cache = \Drupal::cache('data')->get($tree_cid);
      if ($cache && $cache->data) {
        $trees[$tree_cid] = $cache->data;
      }
    }

    if (!isset($trees[$tree_cid])) {
      $min_depth = (isset($parameters['min_depth']) ? $parameters['min_depth'] : 1);
      $result = $this->hierarchyOutlineStorage->getHierarchyMenuTree($hid, $parameters, $min_depth, static::HIERARCHY_MAX_DEPTH);

      // Build an ordered array of links using the query result object.
      $links = array();
      foreach ($result as $link) {
        $link = (array) $link;
        $links[$link['nid']] = $link;
      }
      $active_trail = (isset($parameters['active_trail']) ? $parameters['active_trail'] : array());
      $data['tree'] = $this->buildHierarchyOutlineData($links, $active_trail, $min_depth);
      $data['node_links'] = array();
      $this->hierarchyTreeCollectNodeLinks($data['tree'], $data['node_links']);

      // Cache the data, if it is not already in the cache.
      \Drupal::cache('data')->set($tree_cid, $data, Cache::PERMANENT, array('hid:' . $hid));
      $trees[$tree_cid] = $data;
    }

    return $trees[$tree_cid];
  }

  /**
   * {@inheritdoc}
   */
  public function hierarchyTreeCollectNodeLinks(&$tree, &$node_links) {
    // All hierarchy links are nodes.
    // @todo clean this up.
    foreach ($tree as $key => $v) {
      $nid = $v['link']['nid'];
      $node_links[$nid][$tree[$key]['link']['nid']] = &$tree[$key]['link'];
      $tree[$key]['link']['access'] = FALSE;
      if ($tree[$key]['below']) {
        $this->hierarchyTreeCollectNodeLinks($tree[$key]['below'], $node_links);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hierarchyTreeGetFlat(array $hierarchy_link) {
    if (!isset($this->hierarchyTreeFlattened[$hierarchy_link['nid']])) {
      // Call $this->hierarchyTreeAllData() to take advantage of caching.
      $tree = $this->hierarchyTreeAllData($hierarchy_link['hid'], $hierarchy_link, $hierarchy_link['depth'] + 1);
      $this->hierarchyTreeFlattened[$hierarchy_link['nid']] = array();
      $this->flatHierarchyTree($tree, $this->hierarchyTreeFlattened[$hierarchy_link['nid']]);
    }

    return $this->hierarchyTreeFlattened[$hierarchy_link['nid']];
  }

  /**
   * Recursively converts a tree of menu links to a flat array.
   *
   * @param array $tree
   *   A tree of menu links in an array.
   * @param array $flat
   *   A flat array of the menu links from $tree, passed by reference.
   *
   * @see static::hierarchyTreeGetFlat().
   */
  protected function flatHierarchyTree(array $tree, array &$flat) {
    foreach ($tree as $data) {
      $flat[$data['link']['nid']] = $data['link'];
      if ($data['below']) {
        $this->flatHierarchyTree($data['below'], $flat);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadHierarchyLink($nid, $translate = TRUE) {
    $links = $this->loadHierarchyLinks(array($nid), $translate);
    return isset($links[$nid]) ? $links[$nid] : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function loadHierarchyLinks($nids, $translate = TRUE) {
    $result = $this->hierarchyOutlineStorage->loadMultiple($nids);
    $links = array();
    foreach ($result as $link) {
      if ($translate) {
        $this->hierarchyLinkTranslate($link);
      }
      $links[$link['nid']] = $link;
    }

    return $links;
  }

  /**
   * Do the actual insertion or update. No permissions checking is done here.
   */
  function hierarchySaveNode(&$node) {
    if (!isset($node->nodehierarchy_parents)) {
      return;
    }

    foreach ($node->nodehierarchy_parents as $i => $item) {
      $node->nodehierarchy_parents[$i] = (object)$item;
      $node->nodehierarchy_parents[$i]->cnid = $node->nid;
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

    unset($item->cnid);
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
//    dsm($item);
    if ($item->pnid) {
      $this->insertHierarchy($item);

        $this->insertHierarchy($item);
    }
  }

  public function insertHierarchy($item){
    return $this->hierarchyOutlineStorage->insert($item);
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
   * Get the default menu link values for a new nodehierarchy menu link.
   */
  public function hierarchyDefaultRecord($cnid = NULL, $npid = NULL) {
    return (object)array(
      'pnid' => $npid,
      'weight' => 0,
      'cnid' => $cnid,
    );
  }


  /**
   * {@inheritdoc}
   */
  public function saveHierarchyLink(array $link, $new) {
    // Keep track of Hierarchy IDs for cache clear.
    $affected_hids[$link['hid']] = $link['hid'];
    $link += $this->getLinkDefaults($link['nid']);
    if ($new) {
      // Insert new.
      $parents = $this->getHierarchyParents($link, (array) $this->loadHierarchyLink($link['pid'], FALSE));
      $this->hierarchyOutlineStorage->insert($link, $parents);

      // Update the has_children status of the parent.
      $this->updateParent($link);
    }
    else {
      $original = $this->loadHierarchyLink($link['nid'], FALSE);
      // Using the Hierarchy ID as the key keeps this unique.
      $affected_hids[$original['hid']] = $original['hid'];
      // Handle links that are moving.
      if ($link['hid'] != $original['hid'] || $link['pid'] != $original['pid']) {
        // Update the hid for this page and all children.
        if ($link['pid'] == 0) {
          $link['depth'] = 1;
          $parent = array();
        }
        // In case the form did not specify a proper PID we use the BID as new
        // parent.
        elseif (($parent_link = $this->loadHierarchyLink($link['pid'], FALSE)) && $parent_link['hid'] != $link['hid']) {
          $link['pid'] = $link['hid'];
          $parent = $this->loadHierarchyLink($link['pid'], FALSE);
          $link['depth'] = $parent['depth'] + 1;
        }
        else {
          $parent = $this->loadHierarchyLink($link['pid'], FALSE);
          $link['depth'] = $parent['depth'] + 1;
        }
        $this->setParents($link, $parent);
        $this->moveChildren($link, $original);

        // Update the has_children status of the original parent.
        $this->updateOriginalParent($original);
        // Update the has_children status of the new parent.
        $this->updateParent($link);
      }
      // Update the weight and pid.
      $this->hierarchyOutlineStorage->update($link['nid'], array(
        'weight' => $link['weight'],
        'pid' => $link['pid'],
        'hid' => $link['hid'],
      ));
    }
    $cache_tags = [];
    foreach ($affected_hids as $hid) {
      $cache_tags[] = 'hid:' . $hid;
    }
    \Drupal::cache('data')->deleteTags($cache_tags);
    return $link;
  }

  /**
   * Moves children from the original parent to the updated link.
   *
   * @param array $link
   *   The link being saved.
   * @param array $original
   *   The original parent of $link.
   */
  protected function moveChildren(array $link, array $original) {
    $p = 'p1';
    $expressions = array();
    for ($i = 1; $i <= $link['depth']; $p = 'p' . ++$i) {
      $expressions[] = array($p, ":p_$i", array(":p_$i" => $link[$p]));
    }
    $j = $original['depth'] + 1;
    while ($i <= static::HIERARCHY_MAX_DEPTH && $j <= static::HIERARCHY_MAX_DEPTH) {
      $expressions[] = array('p' . $i++, 'p' . $j++, array());
    }
    while ($i <= static::HIERARCHY_MAX_DEPTH) {
      $expressions[] = array('p' . $i++, 0, array());
    }

    $shift = $link['depth'] - $original['depth'];
    if ($shift > 0) {
      // The order of expressions must be reversed so the new values don't
      // overwrite the old ones before they can be used because "Single-table
      // UPDATE assignments are generally evaluated from left to right"
      // @see http://dev.mysql.com/doc/refman/5.0/en/update.html
      $expressions = array_reverse($expressions);
    }

    $this->hierarchyOutlineStorage->updateMovedChildren($link['hid'], $original, $expressions, $shift);
  }

  /**
   * Sets the has_children flag of the parent of the node.
   *
   * This method is mostly called when a hierarchy link is moved/created etc. So we
   * want to update the has_children flag of the new parent hierarchy link.
   *
   * @param array $link
   *   The hierarchy link, data reflecting its new position, whose new parent we want
   *   to update.
   *
   * @return bool
   *   TRUE if the update was successful (either there is no parent to update,
   *   or the parent was updated successfully), FALSE on failure.
   */
  protected function updateParent(array $link) {
    if ($link['pid'] == 0) {
      // Nothing to update.
      return TRUE;
    }
    return $this->hierarchyOutlineStorage->update($link['pid'], array('has_children' => 1));
  }

  /**
   * Updates the has_children flag of the parent of the original node.
   *
   * This method is called when a hierarchy link is moved or deleted. So we want to
   * update the has_children flag of the parent node.
   *
   * @param array $original
   *   The original link whose parent we want to update.
   *
   * @return bool
   *   TRUE if the update was successful (either there was no original parent to
   *   update, or the original parent was updated successfully), FALSE on
   *   failure.
   */
  protected function updateOriginalParent(array $original) {
    if ($original['pid'] == 0) {
      // There were no parents of this link. Nothing to update.
      return TRUE;
    }
    // Check if $original had at least one child.
    $original_number_of_children = $this->hierarchyOutlineStorage->countOriginalLinkChildren($original);

    $parent_has_children = ((bool) $original_number_of_children) ? 1 : 0;
    // Update the parent. If the original link did not have children, then the
    // parent now does not have children. If the original had children, then the
    // the parent has children now (still).
    return $this->hierarchyOutlineStorage->update($original['pid'], array('has_children' => $parent_has_children));
  }

  /**
   * Sets the p1 through p9 properties for a hierarchy link being saved.
   *
   * @param array $link
   *   The hierarchy link to update.
   * @param array $parent
   *   The parent values to set.
   */
  protected function setParents(array &$link, array $parent) {
    $i = 1;
    while ($i < $link['depth']) {
      $p = 'p' . $i++;
      $link[$p] = $parent[$p];
    }
    $p = 'p' . $i++;
    // The parent (p1 - p9) corresponding to the depth always equals the nid.
    $link[$p] = $link['nid'];
    while ($i <= static::HIERARCHY_MAX_DEPTH) {
      $p = 'p' . $i++;
      $link[$p] = 0;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hierarchyTreeCheckAccess(&$tree, $node_links = array()) {
    if ($node_links) {
      // @todo Extract that into its own method.
      $nids = array_keys($node_links);

      // @todo This should be actually filtering on the desired node status field
      //   language and just fall back to the default language.
      $nids = \Drupal::entityQuery('node')
        ->condition('nid', $nids)
        ->condition('status', 1)
        ->execute();

      foreach ($nids as $nid) {
        foreach ($node_links[$nid] as $mlid => $link) {
          $node_links[$nid][$mlid]['access'] = TRUE;
        }
      }
    }
    $this->doHierarchyTreeCheckAccess($tree);
  }

  /**
   * Sorts the menu tree and recursively checks access for each item.
   */
  protected function doHierarchyTreeCheckAccess(&$tree) {
    $new_tree = array();
    foreach ($tree as $key => $v) {
      $item = &$tree[$key]['link'];
      $this->hierarchyLinkTranslate($item);
      if ($item['access']) {
        if ($tree[$key]['below']) {
          $this->doHierarchyTreeCheckAccess($tree[$key]['below']);
        }
        // The weights are made a uniform 5 digits by adding 50000 as an offset.
        // After calling $this->hierarchyLinkTranslate(), $item['title'] has the
        // translated title. Adding the nid to the end of the index insures that
        // it is unique.
        $new_tree[(50000 + $item['weight']) . ' ' . $item['title'] . ' ' . $item['nid']] = $tree[$key];
      }
    }
    // Sort siblings in the tree based on the weights and localized titles.
    ksort($new_tree);
    $tree = $new_tree;
  }

  /**
   * {@inheritdoc}
   */
  public function hierarchyLinkTranslate(&$link) {
    $node = NULL;
    // Access will already be set in the tree functions.
    if (!isset($link['access'])) {
      $node = $this->entityManager->getStorage('node')->load($link['nid']);
      $link['access'] = $node && $node->access('view');
    }
    // For performance, don't localize a link the user can't access.
    if ($link['access']) {
      // @todo - load the nodes en-mass rather than individually.
      if (!$node) {
        $node = $this->entityManager->getStorage('node')
          ->load($link['nid']);
      }
      // The node label will be the value for the current user's language.
      $link['title'] = $node->label();
      $link['options'] = array();
    }
    return $link;
  }

  /**
   * Sorts and returns the built data representing a hierarchy tree.
   *
   * @param array $links
   *   A flat array of hierarchy links that are part of the hierarchy. Each array element
   *   is an associative array of information about the hierarchy link, containing the
   *   fields from the {hierarchy} table. This array must be ordered depth-first.
   * @param array $parents
   *   An array of the node ID values that are in the path from the current
   *   page to the root of the hierarchy tree.
   * @param int $depth
   *   The minimum depth to include in the returned hierarchy tree.
   *
   * @return array
   *   An array of hierarchy links in the form of a tree. Each item in the tree is an
   *   associative array containing:
   *   - link: The hierarchy link item from $links, with additional element
   *     'in_active_trail' (TRUE if the link ID was in $parents).
   *   - below: An array containing the sub-tree of this item, where each element
   *     is a tree item array with 'link' and 'below' elements. This array will be
   *     empty if the hierarchy link has no items in its sub-tree having a depth
   *     greater than or equal to $depth.
   */
  protected function buildHierarchyOutlineData(array $links, array $parents = array(), $depth = 1) {
    // Reverse the array so we can use the more efficient array_pop() function.
    $links = array_reverse($links);
    return $this->buildHierarchyOutlineRecursive($links, $parents, $depth);
  }

  /**
   * Builds the data representing a hierarchy tree.
   *
   * The function is a bit complex because the rendering of a link depends on
   * the next hierarchy link.
   */
  protected function buildHierarchyOutlineRecursive(&$links, $parents, $depth) {
    $tree = array();
    while ($item = array_pop($links)) {
      // We need to determine if we're on the path to root so we can later build
      // the correct active trail.
      $item['in_active_trail'] = in_array($item['nid'], $parents);
      // Add the current link to the tree.
      $tree[$item['nid']] = array(
        'link' => $item,
        'below' => array(),
      );
      // Look ahead to the next link, but leave it on the array so it's available
      // to other recursive function calls if we return or build a sub-tree.
      $next = end($links);
      // Check whether the next link is the first in a new sub-tree.
      if ($next && $next['depth'] > $depth) {
        // Recursively call buildHierarchyOutlineRecursive to build the sub-tree.
        $tree[$item['nid']]['below'] = $this->buildHierarchyOutlineRecursive($links, $parents, $next['depth']);
        // Fetch next link after filling the sub-tree.
        $next = end($links);
      }
      // Determine if we should exit the loop and $request = return.
      if (!$next || $next['depth'] < $depth) {
        break;
      }
    }
    return $tree;
  }

  /**
   * {@inheritdoc}
   */
  public function hierarchySubtreeData($link) {
    $tree = &drupal_static(__METHOD__, array());

    // Generate a cache ID (cid) specific for this $link.
    $cid = 'hierarchy-links:subtree-cid:' . $link['nid'];

    if (!isset($tree[$cid])) {
      $tree_cid_cache = \Drupal::cache('data')->get($cid);

      if ($tree_cid_cache && $tree_cid_cache->data) {
        // If the cache entry exists, it will just be the cid for the actual data.
        // This avoids duplication of large amounts of data.
        $cache = \Drupal::cache('data')->get($tree_cid_cache->data);

        if ($cache && isset($cache->data)) {
          $data = $cache->data;
        }
      }

      // If the subtree data was not in the cache, $data will be NULL.
      if (!isset($data)) {
        $result = $this->hierarchyOutlineStorage->getHierarchySubtree($link, static::HIERARCHY_MAX_DEPTH);
        $links = array();
        foreach ($result as $item) {
          $links[] = $item;
        }
        $data['tree'] = $this->buildHierarchyOutlineData($links, array(), $link['depth']);
        $data['node_links'] = array();
        $this->hierarchyTreeCollectNodeLinks($data['tree'], $data['node_links']);
        // Compute the real cid for hierarchy subtree data.
        $tree_cid = 'hierarchy-links:subtree-data:' . hash('sha256', serialize($data));
        // Cache the data, if it is not already in the cache.

        if (!\Drupal::cache('data')->get($tree_cid)) {
          \Drupal::cache('data')->set($tree_cid, $data, Cache::PERMANENT, array('hid:' . $link['hid']));
        }
        // Cache the cid of the (shared) data using the hierarchy and item-specific cid.
        \Drupal::cache('data')->set($cid, $tree_cid, Cache::PERMANENT, array('hid:' . $link['hid']));
      }
      // Check access for the current user to each item in the tree.
      $this->hierarchyTreeCheckAccess($data['tree'], $data['node_links']);
      $tree[$cid] = $data['tree'];
    }

    return $tree[$cid];
  }

}
