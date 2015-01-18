<?php

/**
 * @file
 *
 * A module to make nodes hierarchical.
 */

/**
 * Implmentation of hook_views_api().
 */
function nodehierarchy_views_api() {
  return array(
    'api' => 2,
    'path' => drupal_get_path('module', 'nodehierarchy') . '/includes/views',
  );
}

/**
 * Implements hook_simpletest().
 */
function nodehierarchy_simpletest() {
  $dir = drupal_get_path('module', 'nodehierarchy') . '/tests';
  $tests = file_scan_directory($dir, '/\.test$/');
  return array_keys($tests);
}

/**
 * Implements hook_menu().
 */
function nodehierarchy_menu() {
  $items = array();
  $items['admin/stucture/nodehierarchy'] = array(
    'title' => t('Node Hierarchy'),
    'description' => t('Administer default Node Hierarchy settings.'),
    'page callback' => 'drupal_get_form',
    'page arguments' => array('nodehierarchy_admin_settings'),
    'access arguments' => array('administer site configuration'),
    'type' => MENU_NORMAL_ITEM,
  );
  $items['node/%node/children'] = array(
    'title' => t('Children'),
    'page callback' => 'nodehierarchy_view_children',
    'page arguments' => array(1),
    'access callback' => 'nodehierarchy_children_tab_access',
    'access arguments' => array(1),
    'type' => MENU_LOCAL_TASK,
    'weight' => 5,
  );
  return $items;
}

/**
 * Implements hook_menu_alter().
 */
function nodehierarchy_menu_alter(&$items) {
  // Override the menu overview form to handle the potentially large number of links created by node hierarchy.
  $items['admin/structure/menu/manage/%menu']['page arguments'] = array('nodehierarchy_menu_overview_form', 4);
}

/**
 * Children tab access callback.
 */
function nodehierarchy_children_tab_access($node) {
  return node_access('update', $node) && nodehierarchy_node_can_be_parent($node);
}

/**
 * Implements hook_theme().
 */
function nodehierarchy_theme() {
  return array(
    'nodehierarchy_new_child_links' => array(
      'variables' => array('node' => NULL),
    ),
    'nodehierarchy_children_form' => array(
      'render element' => 'form',
    ),
    'nodehierarchy_parent_selector' => array(
      'render element' => 'element',
    ),
    'nodehierarchy_menu_overview_form' => array(
      'render element' => 'form',
    ),
  );
}

/**
 * Implements hook_content_extra_fields().
 */
function nodehierarchy_content_extra_fields($type_name) {
  $extra = array();
  if (nodehierarchy_node_can_be_child($type_name) || nodehierarchy_node_can_be_parent($type_name)) {
    $extra['nodehierarchy'] = array(
      'label' => t('Node Hierarchy settings'),
      'description' => t('Node Hierarchy module form.'),
      'weight' => 10,
    );
  }
  return $extra;
}

/**
 * Helper function generates admin settings page.
 */
function nodehierarchy_admin_settings($form, &$form_state) {
  $form = array();

  // Individual type settings.
  $form['nodehierarchy_types'] = array(
    '#type' => 'fieldset',
    '#title' => t('Node Type Settings'),
    '#description' => t('Settings for individual node types. These can also be set in the !ct section.', array("!ct" => l(t("Content Types"), "admin/structure/types"))),
  );
  foreach (node_type_get_types() as $key => $type) {
    // Individual type settings.
    $form['nodehierarchy_types'][$key] = array(
      '#type' => 'fieldset',
      '#title' => $type->name,
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['nodehierarchy_types'][$key] += _nodehierarchy_get_node_type_settings_form($key, TRUE);
  }

  // Menu generation.
  if (function_exists('menu_parent_options')) {
    $form['nodehierarchy_menu'] = array(
      '#type' => 'fieldset',
      '#title' => t('Node Hierarchy Menu Generation'),
    );
    $form['nodehierarchy_menu']['nodehierarchy_default_menu_name'] = array(
      '#type' => 'select',
      '#title' => t('Default parent menu'),
      '#options' => menu_get_menus(),
      '#default_value' => variable_get('nodehierarchy_default_menu_name', 'navigation'),
      '#description' => t('If a menu is created for a node with no parent the new menu item will appear in this menu.'),
    );

    $form['nodehierarchy_menu']['nodehierarchy_menu_module_edit'] = array(
      '#type' => 'checkbox',
      '#title' => t('Always show hidden Node Hierarchy menu items on the menu overview forms.'),
      '#default_value' => variable_get('nodehierarchy_menu_module_edit', FALSE),
      '#description' => t('Allow disabled nodehierarchy menu items to be edited with regular menu items in the menu overview screen. Turn this off if large Node Hierarchy menus are causing memory errors on menu edit screens.'),
    );
  }

  return system_settings_form($form);
}

/**
 * Implementation of hooks_form_alter().
 *
 * So we don't see preview or delete buttons for hierarchy.
 */
function nodehierarchy_form_alter(&$form, &$form_state, $form_id) {
  global $user;

  $type = isset($form['type']) && isset($form['#node']) ? $form['type']['#value'] : '';

  switch ($form_id) {
    case 'node_type_form':
      $type = $form['old_type']['#value'];

      $form['nodehierarchy'] = array(
        '#type' => 'fieldset',
        '#group' => 'additional_settings',
        '#title' => t('Node Hierarchy'),
        '#weight' => 10,
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#attached' => array(
          'js' => array(drupal_get_path('module', 'nodehierarchy') . '/nodehierarchy.js'),
        ),
      );

      $form['nodehierarchy'] += _nodehierarchy_get_node_type_settings_form($type);
      break;
    case $type . '_node_form':
      $node = isset($form['#node']) ? $form['#node'] : NULL;
      nodehierarchy_set_breadcrumbs($node, TRUE);
      $hierarchy_form = module_invoke_all('nodehierarchy_node_form', $node);
      // if the current user has no nodehierarchy perms, don't show the form
      $access = FALSE;
      foreach (nodehierarchy_permission() as $perm => $info) {
        if (user_access($perm)) {
          $access = TRUE;
          break;
        }
      }
      if ($hierarchy_form) {
        $weight = function_exists('content_extra_field_weight') ? content_extra_field_weight($type, 'nodehierarchy') : 10;
        $form['nodehierarchy'] = array_merge(
          array(
          '#type' => 'fieldset',
          '#title' => t('Node Hierarchy'),
          '#group' => 'additional_settings',
          '#collapsible' => TRUE,
          '#collapsed' => empty($form_state['nodehierarchy_expanded']) ? TRUE : FALSE,
          '#weight' => $weight,
          '#access' => $access,
        ),
        $hierarchy_form);
      }
      break;
    case 'node_delete_confirm':
      // TODO: Fix the descendant count code to deal with multiparent situations.
      if ($count = _nodehierarchy_get_children_count($form['nid']['#value'])) {
        $items = array();
        foreach (_nodehierarchy_get_children_menu_links($form['nid']['#value'], 10) as $child) {
          $items[] = check_plain($child['link_title']);
        }
        if ($count > 10) {
          $items[] = l(t('See all !count children', array('!count' => $count)), 'node/' . $form['nid']['#value'] . '/children');
        }
        $list = theme('item_list', array('items' => $items));
        $description = format_plural($count, 'This node has @count child. Check this box to delete it and all of its descendants as well.', 'This node has @count children. Check this box to delete them and all of their descendants as well.' );
        $description .= t('<p>These children and their decendants will be deleted:!list<p>', array('!list' => $list));
        $form['nodehierarchy_delete_children'] = array(
          '#type' => 'checkbox',
          '#title' => t('Delete descendants'),
          '#description' => $description,
        );
        array_unshift($form['#submit'], 'nodehierarchy_node_delete_submit');
        $form['actions']['#weight'] = 1;
      }
      break;
  }
}

/**
 * Form for editing an entire menu tree at once.
 *
 * Shows for one menu the menu items accessible to the current user and
 * relevant operations. This is a clone of the menu.module function
 * menu_overview_form but with nodehierarchy items not loaded if they don not
 * have a menu presence.
 */
function nodehierarchy_menu_overview_form($form, &$form_state, $menu) {
  if (!module_exists('menu')) {
    return array();
  }

  // Count how many elements are omitted.
  $hidden = db_query("SELECT COUNT(*) FROM {menu_links} ml WHERE ml.menu_name = :ml_menu_name AND ml.module = :ml_module AND ml.hidden != :ml_hidden", array(':ml_menu_name' => $menu['menu_name'], ':ml_module' => 'nodehierarchy', ':ml_hidden' => 0))->fetchField();

  // Use the default menu behaviour unless specified.
  if ($hidden < variable_get('nodehierarchy_hide_disabled_threshold', 100) || isset($_GET['all']) || variable_get('nodehierarchy_menu_module_edit', FALSE)) {
    $form = menu_overview_form($form, $form_state, $menu);
  }
  else {
    global $menu_admin;

    // Fetch all the menu items which are not node hierarchy non-displaying items.
    $sql = "
      SELECT m.load_functions, m.to_arg_functions, m.access_callback, m.access_arguments, m.page_callback, m.page_arguments, m.title, m.title_callback, m.title_arguments, m.type, m.description, ml.*
      FROM {menu_links} ml LEFT JOIN {menu_router} m ON m.path = ml.router_path
      WHERE ml.menu_name = :ml_menu_name
        AND (ml.module != 'nodehierarchy' OR ml.hidden = 0)
      ORDER BY p1 ASC, p2 ASC, p3 ASC, p4 ASC, p5 ASC, p6 ASC, p7 ASC, p8 ASC, p9 ASC";
    $result = db_query($sql, array(':ml_menu_name' => $menu['menu_name']), array('fetch' => PDO::FETCH_ASSOC));
    $list = array();
    foreach ($result as $item) {
      $list[] = $item;
    }
    $tree = menu_tree_data($list);
    $node_links = array();
    menu_tree_collect_node_links($tree, $node_links);
    // We indicate that a menu administrator is running the menu access check.
    $menu_admin = TRUE;
    menu_tree_check_access($tree, $node_links);
    $menu_admin = FALSE;

    $form = _menu_overview_tree_form($tree);
    $form['#menu'] =  $menu;
    if (element_children($form)) {
      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Save configuration'),
      );
    }
    else {
      $form['empty_menu'] = array('#value' => t('There are no menu items yet.'));
    }
    $form['hidden']['#value'] =  $hidden;
    $form['hidden']['#type'] = 'value';

  }

  // Set the form handlers so the behaviour is the same as the regular menu form.
  $form['#submit'][] = 'menu_overview_form_submit';

  return $form;
}


/**
 * Implements hook_form_menu_edit_item_alter().
 *
 * Alter the menu edit screen to limit the available parents according to the rules of node hierachy.
 */
function nodehierarchy_form_menu_edit_item_alter(&$form, &$form_state) {
  // Replace the parent pulldown with the node hierarchy parent selector.
  if ($form['module']['#value'] == 'nodehierarchy') {
    // Add the js to hide/show the menu selector.
    drupal_add_js(drupal_get_path("module", "nodehierarchy") . '/nodehierarchy.js');

    $menu = menu_load($form['original_item']['#value']['menu_name']);
    $item = menu_link_load($form['mlid']['#value']);
    if (!$item) {
      $item = array('link_title' => '', 'mlid' => 0, 'plid' => 0, 'menu_name' => $menu['menu_name'], 'weight' => 0, 'link_path' => '', 'options' => array(), 'module' => 'menu', 'expanded' => 0, 'hidden' => 0, 'has_children' => 0);
    }
    $menus = menu_get_menus();

    // Get the node for this menu item.
    list(, $nid) = explode('/', $form['link_path']['#value']);
    $node = node_load($nid);

    // Load the parent menu item if any (to get the parent node id).
    $parent_menu = _nodehierarchy_load_menu_link($item['plid']);

    $default = $item['menu_name'] .':'. (isset($item['plid']) ? $item['plid'] : 0);
    if (!isset($options[$default])) {
      $default = 'navigation:0';
    }

    // Add a class to the menu fieldset to allow the js to work.
    $form['#attributes']['class'][] = 'nodehierarchy-menu-link';

    // Replace the parent pulldown.
    unset($form['parent']);
    $form['pnid'] = _nodehierarchy_get_parent_selector($node->type, $parent_menu['nid'], $nid);

    // Add the menu selector in case the user picks none for the parent.
    $form['menu_name'] = array(
      '#type' => 'select',
      '#title' => 'Menu',
      '#prefix' => '<div class="nodehierarchy-menu-name">',
      '#suffix' => '</div>',
      '#options' => $menus,
      '#default_value' => $default,
      '#description' => t('If you do not pick a parent for this node its menu item will appear at the top level of this menu.'),
    );
    // Set some weights so that the weight pulldown still appears at the bottom.
    $form['menu_name']['#weight'] = 20;
    $form['weight']['#weight'] = 30;

    $form['#submit'] = array_merge(array('nodehierarchy_form_menu_edit_item_submit'), $form['#submit']);
  }
}

/**
 * Submit the menu_edit_item form.
 */
function nodehierarchy_form_menu_edit_item_submit($form, &$form_state) {
  // Menu name and parent are in two different fields after our alter, so we glue them back together.
  $form_state['values']['parent'] = $form_state['values']['menu_name'] . ':' . _nodehierarchy_get_node_mlid($form_state['values']['pnid'], TRUE);
}

/**
 * Theme the menu overview form into a table respecting the node hierarchy rules.
 *
 * @ingroup themeable
 */
function theme_nodehierarchy_menu_overview_form($variables) {
  $form = $variables['form'];

  drupal_add_js(drupal_get_path("module", "nodehierarchy") . '/nodehierarchy.tabledrag.js');
  drupal_add_css(drupal_get_path('module', 'nodehierarchy') . '/nodehierarchy.css');

  // Show a notice if items have been omitted.
  if (!empty($form['hidden']['#value'])) {
    $msg = t('@hidden disabled menu items generated by Node Hierarchy have been ommited from this list.', array('@hidden' => $form['hidden']['#value']));
    $msg .= ' ' .  l('Click here to show all items', $_GET['q'], array('query' => array('all' => TRUE))) . '.';
    if (user_access('administer site configuration')) {
      $msg .= ' ' .  t('To permentently show all items, edit your !settings.', array('!settings' => l(t('Node hierarchy settings'), 'admin/config/nodehierarchy')));
    }
    drupal_set_message($msg, 'warning');
  }

  $js_settings = array();
  $rows = array();

  foreach (element_children($form) as $mlid) {
    if (isset($form[$mlid]['hidden'])) {
      $element = $form[$mlid];
      if ($element['#item']['module'] == 'nodehierarchy') {
        list(, $nid) = explode('/', $element['#item']['link_path']);
        $type = db_query_range('SELECT type FROM {node} WHERE nid = :nid', 0, 1, array(':nid' => $nid))->fetchField();
        $js_settings['nodehierarchyMenuDrag']['allowed-parents'][$type] = nodehierarchy_get_allowed_parent_types($type);
        // Add special classes to be used for nodehierarchy.js.
        $variables['form'][$mlid]['#attributes']['class'][] = 'nodehierarchy-menu-item';
        $variables['form'][$mlid]['#attributes']['class'][] = 'node-type-' . $type;
        $variables['form'][$mlid]['#attributes']['id'] = 'mlid-' . $element['#item']['mlid'];
      }
    }
  }

  drupal_add_js($js_settings, array('type' => 'setting', 'scope' => JS_DEFAULT));

  return theme_menu_overview_form($variables);
}


/**
 * Implements hook_node_insert().
 */
function nodehierarchy_node_insert($node) {
  nodehierarchy_insert_node($node);
}

/**
 * Implements hook_node_update().
 */
function nodehierarchy_node_update($node) {
  nodehierarchy_update_node($node);
}

/**
 * Implements hook_node_prepare().
 */
function nodehierarchy_node_prepare($node) {
  return nodehierarchy_prepare_node($node);
}

/**
 * Implements hook_node_load().
 */
function nodehierarchy_node_load($node, $types) {
  foreach ($node as $nid => $node) {
    nodehierarchy_load_node($node);
  }
}

/**
 * Implements hook_node_delete().
 */
function nodehierarchy_node_delete($node) {
  nodehierarchy_delete_node($node);
}

/**
 * Implements hook_node_view().
 */
function nodehierarchy_node_view($node, $view_mode = 'full') {
  if ($view_mode == 'full') {
    nodehierarchy_set_breadcrumbs($node);
  }
}

/**
 * Implmentation of hook_nodeapi().
 */
function nodehierarchy_nodeapi_OLD(&$node, $op, $teaser = NULL, $page = NULL) { }

/**
 * Get the node edit form for nodehierarchy.
 */
function nodehierarchy_nodehierarchy_node_form($node) {
  global $user;

  $form = array();
  // If this node type can be a child.
  if (nodehierarchy_node_can_be_child($node) || nodehierarchy_node_can_be_parent($node)) {
    // Save the old value of the node's parent.
    $value = empty($node->nodehierarchy_old_menu_links) ? null : $node->nodehierarchy_old_menu_links;
    $form['nodehierarchy_old_menu_links'] = array(
      '#type' => 'value',
      '#value' => $value,
    );

    // if the current user can edit the current node's hierarchy settings (or create new children)
    $can_set_parent =
        user_access('edit all node parents') ||
        ($node->nid == NULL && user_access('create child nodes')) ||
        ($node->uid == $user->uid && user_access('edit own node parents'));

    if ($can_set_parent) {
      drupal_add_js(drupal_get_path("module", "nodehierarchy") . '/nodehierarchy.js');

      $form['nodehierarchy_menu_links'] = array('#tree' => TRUE);
      $multiple = variable_get('nh_multiple_' . $node->type, 0);

      $count = 2;

      foreach ((array)$node->nodehierarchy_menu_links as $key => $menu_link) {
        $form['nodehierarchy_menu_links'][$key] = _nodehierarchy_node_parent_form_items($node, $key, $menu_link);
        if ($multiple && $key == 0) {
          $form['nodehierarchy_menu_links'][$key]['#title'] = t('Primary Parent');
          $form['nodehierarchy_menu_links'][$key]['#description'] = t('The primary parent is the one which will appear in this node\'s breadcrumb trail and whose menu item will be expanded for this node.');
        }
        elseif ($multiple) {
          $form['nodehierarchy_menu_links'][$key]['#title'] = t('Parent !num', array('!num' => $count++));
        }
      }
      if ($multiple) {
        $form['nodehierarchy_menu_links'][$key + 1] = _nodehierarchy_node_parent_form_items($node, $key + 1, _nodehierarchy_default_menu_link(isset($node->nid) ? $node->nid : NULL));
        $form['nodehierarchy_menu_links'][$key + 1]['#title'] = t('Add a parent');
        $form['nodehierarchy_menu_links'][$key + 1]['#collapsible'] =  $form['nodehierarchy_menu_links'][$key + 1]['#collapsed'] = TRUE;
        $form['nodehierarchy_menu_links'][$key + 1]['add_another'] = array(
          '#type' => 'submit',
          '#value' => t('Add'),
          '#weight' => 10,
          '#submit' => array('nodehierarchy_node_form_add_parent'),
        );
        unset($form['nodehierarchy_menu_links'][$key + 1]['remove']);
      }
    }
  }
  return $form;
}

/**
 * Submit the form after 'add parent' has been clicked. Don't save anything just rebuild the node and the new parent will show up as normal.
 */
function nodehierarchy_node_form_add_parent($form, &$form_state) {
  $node = node_form_submit_build_node($form, $form_state);
  $form_state['nodehierarchy_expanded'] = TRUE;
}


/**
 * Get the parent and menu setting for items for a given parent menu_link.
 */
function _nodehierarchy_node_parent_form_items($node, $key, $menu_link) {
  // Wrap the item in a div for js purposes
  $item = array(
    '#type' => 'fieldset',
    '#title' => t('Parent'),
    '#tree' => TRUE,
    '#prefix' => '<div class="nodehierarchy-menu-link">',
    '#suffix' => '</div>',
  );

  // If a node can be a child of another add a selector to pick the parent. Otherwise set the parent to 0.
  if (nodehierarchy_node_can_be_child($node)) {
    $item['pnid'] = _nodehierarchy_get_parent_selector($node->type, empty($menu_link['pnid']) ? null : $menu_link['pnid'], empty($node->nid) ? null : $node->nid);
    $item['pnid']['#weight'] = -1;
  }
  else {
    $item['pnid'] = array(
      '#type' => 'value',
      '#value' => 0,
    );
  }

  $item['mlid'] = array(
    '#type' => 'value',
    '#value' => $menu_link['mlid'],
  );
  $create_menu = variable_get('nh_createmenu_' . $node->type, 'optional_no');
  // Prevent menus from being created for non-primary parents. That prevernts weirdness
  // caused by Drupal 6 core's inconsisnency of defining the active menu trail when there
  // menu items pointing to the same node.
  // TODO: find a way around the weirdness and reenable this.
  if ($key > 0) {
    $create_menu = 'never';
  }
  if (
        (user_access('administer menus') || user_access('customize nodehierarchy menus')) &&
        ($create_menu !== 'never')
    ) {
    if ($create_menu == 'optional_yes' || $create_menu == 'optional_no') {
      $item['enabled'] = array(
        '#type' => 'checkbox',
        '#title' => 'Show in menu',
        '#attributes' => array('class' => array('nodehierarchy-menu-enable')),
        '#default_value' => $menu_link['enabled'],
        '#description' => t('All of this node\'s ancestors must have this option selected as well for this item to show in the menu.'),
      );
    }

    $item['menu_settings'] = array(
      '#prefix' => '<div class="nodehierarchy-menu-settings">',
      '#suffix' => '</div>',
      '#tree' => FALSE,
    );
    $item['menu_settings']['menu_name'] = array(
      '#type' => 'select',
      '#title' => 'Menu',
      '#prefix' => '<div class="nodehierarchy-menu-name">',
      '#suffix' => '</div>',
      '#options' => menu_get_menus(),
      '#default_value' => $menu_link['menu_name'],
      '#description' => t('If you do not pick a parent for this node its menu item will appear at the top level of this menu.'),
      '#parents' => array('nodehierarchy_menu_links', $key, 'menu_name'),
    );
    $item['menu_settings']['customized'] = array(
      '#type' => 'checkbox',
      '#attributes' => array('class' => array('nodehierarchy-menu-customize')),
      '#title' => 'Customize menu title',
      '#default_value' => $menu_link['customized'],
      '#return_value' => 1,
      '#parents' => array('nodehierarchy_menu_links', $key, 'customized'),
      '#description' => t('Specify a name for this node\'s menu item that is something other than the node\'s title. Leave unchecked to use the node\'s title.'),
    );
    $item['menu_settings']['link_title'] = array(
      '#type' => 'textfield',
      '#prefix' => '<div class="nodehierarchy-menu-title">',
      '#suffix' => '</div>',
      '#title' => t('Menu link title'),
      '#default_value' => empty($menu_link['link_title']) ? '' : $menu_link['link_title'],
      '#description' => t('The link text corresponding to this item that should appear in the menu.'),
      '#parents' => array('nodehierarchy_menu_links', $key, 'link_title'),
    );
    $item['menu_settings']['expanded'] = array(
      '#type' => 'checkbox',
      '#title' => t('Expand Menu Item'),
      '#default_value' => empty($menu_link['expanded']) ? '' : $menu_link['expanded'],
      '#description' => t('If selected and this menu item has children, the menu will always appear expanded.'),
      '#parents' => array('nodehierarchy_menu_links', $key, 'expanded'),
    );
    $item['menu_settings']['description'] = array(
      '#type' => 'textarea',
      '#title' => t('Menu Item Description'),
      '#default_value' => isset($menu_link['options']['attributes']['title']) ? $menu_link['options']['attributes']['title'] : '',
      '#rows' => 1,
      '#description' => t('The description displayed when hovering over a menu item. Hold your mouse over <a href="#" title="This is where the description will appear.">this link</a> for a demonstration.'),
      '#parents' => array('nodehierarchy_menu_links', $key, 'description'),
    );
  }
  // Add the delete menu item checkbox if this is a pre-existing parent item.
  if ($key > 0) {
    $item['remove'] = array(
      '#type' => 'checkbox',
      '#title' => t('Remove this parent'),
      '#default_value' => isset($menu_link['remove']) ? $menu_link['remove'] : '',
      '#description' => t('Remove this parent from this node. This will not delete the parent node.'),
      '#attributes' => array('class' => array('nodehierarchy-parent-delete')),
    );
  }

  // Add the uneditable values so they're saved.
  foreach ($menu_link as $i => $value) {
    if (!isset($item[$i]) && !isset($item['menu_settings'][$i])) {
      $item[$i] = array(
        '#type' => 'value',
        '#default_value' => $value,
        '#parents' => array('nodehierarchy_menu_links', $key, $i),
      );
    }
  }

  return $item;
}

/**
 * Update a node's parent and create menus etc.
 */
function nodehierarchy_update_node(&$node) {
  global $user;
  if (user_access('edit all node parents') || ($node->uid == $user->uid && user_access('edit own node parents'))) {
    _nodehierarchy_save_node($node);
  }
}

/**
 * Insert a node. Create parents and menus etc.
 */
function nodehierarchy_insert_node(&$node) {
  if (user_access('create child nodes')) {
    _nodehierarchy_save_node($node);
  }
}

/**
 * Do the actual insertion or update. No permissions checking is done here.
 */
function _nodehierarchy_save_node(&$node) {

  if (!isset($node->nodehierarchy_menu_links)) {
    return;
  }

  // Update all of the pre-existing or default parents.
  for ($i = 0; $i < count($node->nodehierarchy_menu_links); $i++) {
    // Get the plid from the parent node id.
    $node->nodehierarchy_menu_links[$i]['plid'] = _nodehierarchy_get_node_mlid($node->nodehierarchy_menu_links[$i]['pnid'], TRUE);

    // Convert NULL to 0 to distinguish between a new menu item (null) and one with parent set to none (0).
    $node->nodehierarchy_menu_links[$i]['plid'] = $node->nodehierarchy_menu_links[$i]['plid'] ? $node->nodehierarchy_menu_links[$i]['plid'] : 0;

    // Mark the menu item to be removed if it is null (ie: not enabled and with a null parent).
    if (empty($node->nodehierarchy_menu_links[$i]['enabled']) && empty($node->nodehierarchy_menu_links[$i]['plid'])) {
      $node->nodehierarchy_menu_links[$i]['remove'] = TRUE;
    }

    // If the node type cannot be a parent, and has no parent itself, then do not save a link.
    if (isset($node->nodehierarchy_menu_links[$i]['remove'])) {

      // We can only delete menu links that don't have children associated with them.
      if (!_nodehierarchy_get_children_count_plid($node->nodehierarchy_menu_links[$i]['mlid'])) {
        // Delete the the menu if it exists.
        if ($node->nodehierarchy_menu_links[$i]['mlid']) {
          nodehierarchy_delete_node_nodehierarchy_menu_link($node->nodehierarchy_menu_links[$i]['mlid']);
        }
        // Do not save a new menu_link.
        continue;
      }
      // Otherwise simply set the parent to none and disable the menu.
      else {
        $node->nodehierarchy_menu_links[$i]['enabled'] = $node->nodehierarchy_menu_links[$i]['plid'] = 0;
      }
    }

    // If enabled is selected, then reverse it for hidden.
    if (isset($node->nodehierarchy_menu_links[$i]['enabled'])) {
      $node->nodehierarchy_menu_links[$i]['hidden'] = (int) !$node->nodehierarchy_menu_links[$i]['enabled'];
    }

    // Set the menu title to the node title unless it has been customized.
    if (!$node->nodehierarchy_menu_links[$i]['customized']) {
      $node->nodehierarchy_menu_links[$i]['link_title'] = $node->title;
    }

    if (isset($node->nodehierarchy_menu_links[$i]['description'])) {
      $node->nodehierarchy_menu_links[$i]['options']['attributes']['title'] = $node->nodehierarchy_menu_links[$i]['description'];
    }


    // Update the paths (needed for new nodes).
    $node->nodehierarchy_menu_links[$i]['nid'] = $node->nid;
    $node->nodehierarchy_menu_links[$i]['link_path'] = 'node/' . $node->nid;

    // Do the actual save.
    _nodehierarchy_save_menu_link($node->nodehierarchy_menu_links[$i]);
  }
}

/**
 * Load a node's menu links when the node is loaded.
 */
function nodehierarchy_load_node(&$node) {
  $nid = empty($node->nid) ? null : $node->nid;
  $node->nodehierarchy_menu_links = _nodehierarchy_get_node_menu_links($nid);
}

/**
 * Set a default parent menu link the node is loaded.
 */
function nodehierarchy_prepare_node(&$node) {
  // Cannot use module_invoke_all because it doesn't support references.
  foreach (module_implements('nodehierarchy_default_parents') as $module) {
    $function = $module . '_nodehierarchy_default_parents';
    $function($node);
  }
}

/**
 * Set the default parents for a node.
 */
function nodehierarchy_nodehierarchy_default_parents(&$node) {
  $plid = NULL;

  if (nodehierarchy_node_can_be_child($node) || nodehierarchy_node_can_be_parent($node)) {
    if (!isset($node->nodehierarchy_menu_links) || empty($node->nodehierarchy_menu_links)) {
      // Should this menu item be enabled or not.
      $create_menu = variable_get('nh_createmenu_' . $node->type, 'optional_no');
      $enabled = ($create_menu == 'optional_yes' || $create_menu == 'always');

      // Create a default menu_link object.
      $nid = empty($node->nid) ? null : $node->nid;
      $menu_link = _nodehierarchy_default_menu_link($nid, 0, $enabled);

      // Set the type default if there is one.
      if (empty($node->nid)) {
        $default = variable_get('nh_defaultparent_' . $node->type, 0);
        // Get the parent node id from passed in from the get params.
        $pnid = !empty($_GET['parent']) ? (int) $_GET['parent'] : $default;
        // Get the parent from the get string. User must have update perms for parent unless it is the default.
        if ($pnid && $parent_node = node_load($pnid)) {
          if (nodehierarchy_node_can_be_parent($parent_node) && (user_access('create child of any parent') || node_access("update", $parent_node) || $parent_node->nid == $default)) {
            $menu_link['pnid'] = $pnid;
          }
        }
      }

      $node->nodehierarchy_menu_links[] = $menu_link;
    }
  }
}

/**
 * Delete the nodehierarchy information when a node is deleted.
 */
function nodehierarchy_delete_node($node) {
  nodehierarchy_delete_node_nodehierarchy_menu_links($node->nid);
}

/**
 * Submit function for the node delete confirm form.
 */
function nodehierarchy_node_delete_submit($form, $form_state) {
  $form_values = $form_state['values'];
  if ($form_values['confirm'] && $form_values['nodehierarchy_delete_children']) {
    nodehierarchy_delete_descendants($form_values['nid']);
  }
}

/**
 * Delete all of the descendants of the given node.
 *
 * This is not very scalable and should probably be replaced by a version which uses batch processing.
 */
function nodehierarchy_delete_descendants($nid) {
  foreach (_nodehierarchy_get_children_menu_links($nid) as $child) {
    nodehierarchy_delete_descendants($child['nid']);
    node_delete($child['nid']);
  }
}

/**
 * Get the nodehierarchy setting form for a particular node type.
 */
function _nodehierarchy_get_node_type_settings_form($key, $append_key = FALSE) {
  $form              = array();
  $form['nh_allowchild'] = array(
    '#type' => 'checkboxes',
    '#title' => t('Allowed child node types'),
    '#options' => node_type_get_names(),
    '#default_value' => nodehierarchy_get_allowed_child_types($key),
    '#description' => t('Node types which can be created as child nodes of this node type.'),
  );
  $form['nh_defaultparent'] = _nodehierarchy_get_parent_selector($key, variable_get('nh_defaultparent_' . $key, 0));
  $form['nh_defaultparent']['#title'] = t('Default Parent');

  $form['nh_createmenu'] = array(
    '#type' => 'radios',
    '#title' => t('Show item in menu'),
    '#default_value' => variable_get('nh_createmenu_' . $key, 'optional_no'),
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
    '#default_value' => variable_get('nh_multiple_' . $key, 0),
    '#description' => t('Can nodes of this type have multiple parents?.'),
  );

  $form += module_invoke_all('nodehierarchy_node_type_settings_form', $key);

  // If we need to append the node type key to the form elements, we do so.
  if ($append_key) {
    // Appending the key does not work recursively, so fieldsets etc. are not supported.
    foreach (element_children($form) as $form_key) {
      $form[$form_key . '_' . $key] = $form[$form_key];
      unset($form[$form_key]);
    }
  }
  return $form;
}

/**
 * Can a node be a child.
 */
function nodehierarchy_node_can_be_child($node) {
  $type = is_object($node) ? $node->type : $node;
  return count(nodehierarchy_get_allowed_parent_types($type));
}

/**
 * Can a node be a parent.
 */
function nodehierarchy_node_can_be_parent($node) {
  $type = is_object($node) ? $node->type : $node;
  return count(nodehierarchy_get_allowed_child_types($type));
}

/**
 * Determine if a given node can be a child of another given node.
 *
 * @param $parent
 *    The potentential parent node (can be null for any node).
 * @param $child
 *    The potential child node (can be null for any node).
 * @return
 *   Boolean. Whether second node can be a child of the first.
 */
function nodehierarchy_node_can_be_child_of($parent = NULL, $child = NULL) {
  return in_array($child->type, nodehierarchy_get_allowed_child_types($parent->type));
}

/**
 * Get the allwed parent types for the given child type.
 */
function nodehierarchy_get_allowed_parent_types($child_type = NULL) {
  static $alowed_types = array();

  // Static cache the results because this may be called many times for the same type on the menu overview screen.
  if (!isset($alowed_types[$child_type])) {
    $parent_types = array();
    foreach (node_type_get_types() as $type => $info) {
      $allowed_children = array_filter(variable_get('nh_allowchild_' . $type, array()));
      if ((empty($child_type) && !empty($allowed_children)) || (in_array($child_type, (array) $allowed_children, TRUE))) {
        $parent_types[] = $type;
      }
    }
    $alowed_types[$child_type] = array_unique($parent_types);
  }
  return $alowed_types[$child_type];
}

/**
 * Get the allwed parent types for the given child type.
 */
function nodehierarchy_get_allowed_child_types($parent_type) {
  $child_types = array_filter(variable_get('nh_allowchild_' . $parent_type, array()));
  return array_unique($child_types);
}

/**
 * Display the children tab.
 */
function nodehierarchy_view_children($node) {
  drupal_set_title(t('Children of %t', array('%t' => $node->title)), PASS_THROUGH);
  nodehierarchy_set_breadcrumbs($node, TRUE);
  $out = drupal_get_form('nodehierarchy_children_form', $node);
  $out[] = theme('nodehierarchy_new_child_links', array('node' => $node));
  return $out;
}

/**
 * Built the children tab form.
 */
function nodehierarchy_children_form($form, &$form_state, $node) {
  $form = array();
  $children_links = _nodehierarchy_get_children_menu_links($node->nid, FALSE);
  $form['children'] = array('#tree' => TRUE);
  $type_names = node_type_get_names();
  foreach ($children_links as $child_link) {
    list(, $nid) = explode('/', $child_link['link_path']);
    if ($child = node_load($nid)) {
      $child_item = array();
      $child_item['menu_link']  = array(
        '#type' => 'value',
        '#value' => $child_link,
      );
      $child_item['node']       = array(
        '#type' => 'value',
        '#value' => $child,
      );
      $child_item['title']      = array('#type' => 'markup', '#markup' => l($child->title, $child_link['link_path']));
      $child_item['type']       = array('#type' => 'markup', '#markup' => $type_names[$child->type]);

      $child_item['weight']     = array(
        '#type' => 'weight',
        '#delta' => 50,
        '#default_value' => isset($form_state[$child_link['mlid']]['weight']) ? $form_state[$child_link['mlid']]['weight'] : $child_link['weight'],
      );

      $form['children'][$child_link['mlid']] = $child_item;
    }
  }
  if (element_children($form['children'])) {
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save child order'),
    );
  }
  else {
    $form['no_children'] = array('#type' => 'markup', '#markup' => t('This node has no children.'));
  }
  return $form;
}

/**
 * Submit the children tab form.
 */
function nodehierarchy_children_form_submit($form, &$form_state) {
  $updated_items = array();
  foreach (element_children($form['children']) as $mlid) {
    if (isset($form['children'][$mlid]['menu_link'])) {
      $element = $form['children'][$mlid];
      if ($element['weight']['#value'] != $element['weight']['#default_value']) {
        $element['menu_link']['#value']['weight'] = $element['weight']['#value'];
        $updated_items[$mlid] = $element['menu_link']['#value'];
      }
    }
  }

  // Save all our changed items to the database.
  foreach ($updated_items as $item) {
    menu_link_save($item);
  }
}

/**
 * Display the children tab form.
 */
function theme_nodehierarchy_children_form($variables) {
  $form = $variables['form'];
  drupal_add_tabledrag('children-list', 'order', 'sibling', 'menu-weight');

  $colspan = module_exists('nodeaccess') ? '4' : '3';

  $header = array(
    t('Title'),
    t('Type'),
    t('Weight'),
    array(
      'data' => t('Operations'),
      'colspan' => $colspan,
    ),
  );

  $rows = array();
  foreach (element_children($form['children']) as $mlid) {
    $element = &$form['children'][$mlid];

    // Add special classes to be used for tabledrag.js.
    $element['weight']['#attributes']['class'] = array('menu-weight');

    $node = $element['node']['#value'];
    $row = array();
    $row[] = drupal_render($element['title']);
    $row[] = drupal_render($element['type']);
    $row[] = drupal_render($element['weight']);
    $row[] = node_access('update', $node) ? l(t('edit'), 'node/' . $node->nid . '/edit') : '';
    $row[] = node_access('delete', $node) ? l(t('delete'), 'node/' . $node->nid . '/delete') : '';

    $row[] = nodehierarchy_children_tab_access($node) ? l(t('children'), 'node/' . $node->nid . '/children') : '';
    if (module_exists('nodeaccess')) {
      $row[] = nodeaccess_access('grant', $node) ? l(t('grant'), 'node/' . $node->nid . '/grant') : '';
    }

    $row = array_merge(array('data' => $row), $element['#attributes']);
    $row['class'][] = 'draggable';
    $rows[] = $row;
  }
  $output = '';
  if ($rows) {
    $output .= theme('table', array('header' => $header, 'rows' => $rows, 'attributes' => array('id' => 'children-list')));
  }
  $output .= drupal_render_children($form);
  return $output;
}

/**
 * Get the children of the given node.
 */
function _nodehierarchy_get_children_menu_links($pnid, $limit = FALSE) {
  $children = array();
  $query = "
       SELECT nh_menu_links.*
         FROM {node} node
    LEFT JOIN {nodehierarchy_menu_links} nodehierarchy_menu_links ON node.nid = nodehierarchy_menu_links.nid
    LEFT JOIN {menu_links} nh_menu_links ON nodehierarchy_menu_links.mlid = nh_menu_links.mlid
    LEFT JOIN {nodehierarchy_menu_links} nh_parent ON nh_menu_links.plid = nh_parent.mlid
        WHERE (nh_parent.nid = :pnid)
     ORDER BY nh_menu_links.weight ASC";

  if ($limit) {
    $query .= " LIMIT $limit";
  }

  $result = db_query($query, array(':pnid' => $pnid), array('fetch' => PDO::FETCH_ASSOC));
  foreach ($result as $item) {
    $item['pnid'] = $pnid;
    $children[] = _nodehierarchy_prepare_menu_link($item);
  }
  return $children;
}

/**
 * Count the children of the given node.
 */
function _nodehierarchy_get_children_count($parent) {
  if ($plid = _nodehierarchy_get_node_mlid($parent)) {
    return _nodehierarchy_get_children_count_plid($plid);
  }
  return 0;
}

/**
 * Count the descendants of the given node.
 */
function _nodehierarchy_get_descendant_count($parent) {
  if ($plids = _nodehierarchy_get_node_mlids($parent)) {
    $where = array();
    $args = array();
    // Build all nine ORs to check if the plid is anywhere in the descendent paths.
    for ($i = 1; $i < MENU_MAX_DEPTH; $i++) {
      foreach ($plids as $plid) {
        $where[] = "p$i = %d";
        $args[] = $plid;
      }
    }
    // Add one more plid for the exclusion clause.
    $args[] = $plid;
    return db_query("SELECT count(mlid) as descendent_count FROM {menu_links} WHERE (" . implode(' OR ', $where) . ") AND mlid != %d AND module = 'nodehierarchy'", $args)->fetchField();
  }
  return 0;
}


/**
 * Count the children of the given menu link.
 */
function _nodehierarchy_get_children_count_plid($plid) {
  if ($plid) {
    $out = db_query("SELECT count(mlid) as children_count FROM {menu_links} WHERE module = :module AND plid = :plid AND router_path = :router_path", array(':module' => 'nodehierarchy', ':plid' => $plid, ':router_path' => 'node/%'))->fetchField();
    return $out;
  }
  return 0;
}

/**
 * Save a menu link with changes if needed.
 */
function _nodehierarchy_save_menu_link(&$menu_link) {
  // Item is being moved to a new parent.
  $old_plid = _nodehierarchy_get_plid_from_mlid($menu_link['mlid']);
  if ($old_plid !== (int) $menu_link['plid']) {
    // Get the next weight for the new parent.
    $menu_link['weight'] = _nodehierarchy_get_menu_link_next_child_weight($menu_link['plid']);
  }

  // Save the parent
  menu_link_save($menu_link);
  // Create the link reference.
  _nodehierarchy_create_nodehierarchy_menu_link_reference($menu_link);
}

/**
 * Create a link from the node to its menu item.
 *
 * This pivot table can be used for more efficiently joining to the menu links table for views integration.
 */
function _nodehierarchy_create_nodehierarchy_menu_link_reference($menu_link) {
  if (!db_query("SELECT mlid FROM {nodehierarchy_menu_links} WHERE mlid = :mlid", array(':mlid' => $menu_link['mlid']))->fetchField()) {
    drupal_write_record('nodehierarchy_menu_links', $menu_link);
  }
}

/**
 * Get the menu link id for the given node.
 */
function _nodehierarchy_get_node_mlids($nid) {
  $out = array();
  $result = db_query("SELECT mlid FROM {menu_links} WHERE module = :module AND link_path = :link_path ORDER BY mlid", array(':module' => 'nodehierarchy', ':link_path' => 'node/' . $nid), array('fetch' => PDO::FETCH_ASSOC));
  foreach ($result as $link) {
    $out[] = $link['mlid'];
  }
  return $out;
}

/**
 * Get the primary menu link id for the given node. Optionally create one if needed.
 */
function _nodehierarchy_get_node_mlid($nid, $create = FALSE) {
  $out = NULL;

  if ($nid) {
    $out = db_query("SELECT mlid FROM {menu_links} WHERE module = :module AND link_path = :link_path ORDER BY mlid LIMIT 1", array(':module' => 'nodehierarchy', ':link_path' => 'node/' . $nid))->fetchField();

    // Create a new menu item if needed.
    if ($create && !$out) {
      $menu_link = _nodehierarchy_create_node_menu_link($nid);
      $out = $menu_link['mlid'];
    }
  }
  return $out;
}

/**
 * Get all the menu links for the given node.
 */
function _nodehierarchy_get_node_menu_links($nid, $limit = NULL) {
  $limit_clause = $limit ? ' LIMIT ' . $limit : '';

  $result = db_query("SELECT parent.nid as pnid, ml.* FROM {menu_links} ml LEFT JOIN {nodehierarchy_menu_links} parent on parent.mlid = ml.plid WHERE module = 'nodehierarchy' AND link_path = :path ORDER BY mlid" . $limit_clause, array(':path' => 'node/' . $nid), array('fetch' => PDO::FETCH_ASSOC));
  $out = array();
  foreach ($result as $item) {
    $out[] = _nodehierarchy_prepare_menu_link($item);
  }
  return $out;
}



/**
 * Get the primary menu link for the given node.
 */
function _nodehierarchy_get_node_menu_link($nid) {
  $out = _nodehierarchy_get_node_menu_links($nid, 1);
  return empty($out) ? NULL : $out[0];
}



/**
 * Get the parent nodes for the given node.
 */
function nodehierarchy_get_node_parent_nids($nid) {
  $out = array();
  $menu_links = _nodehierarchy_get_node_menu_links($nid);
  foreach ($menu_links as $menu_link) {
    $parent_link = _nodehierarchy_load_menu_link($menu_link['plid']);
    $out[] = $parent_link['nid'];
  }
  return $out;
}

/**
 * Get the parent nodes for the given node.
 */
function nodehierarchy_get_node_parents($nid) {
  $out = array();
  $menu_links = _nodehierarchy_get_node_menu_links($nid);
  foreach ($menu_links as $menu_link) {
    $parent_link = _nodehierarchy_load_menu_link($menu_link['plid']);
    $out[] = node_load($parent_link['nid']);
  }
  return $out;
}

/**
 * Get the primary parent node for the given node.
 */
function nodehierarchy_get_node_parent($nid) {
  $out = array();
  $menu_links = _nodehierarchy_get_node_menu_links($nid);
  foreach ($menu_links as $menu_link) {
    $parent_link = _nodehierarchy_load_menu_link($menu_link['plid']);
    return node_load($parent_link['nid']);
  }
  return NULL;
}

/**
 * Get the ancestor nodes for the given node.
 */
function nodehierarchy_get_node_ancestor_nids($nid) {
  $out = array();
  $menu_links = _nodehierarchy_get_node_ancestor_menu_links($nid);
  foreach ($menu_links as $trail) {
    foreach ($trail as $menu_link) {
      $out[] = $menu_link['nid'];
    }
  }
  return $out;
}

/**
 * Get the ancestor nodes for the given node.
 */
function nodehierarchy_get_node_primary_ancestor_nids($nid) {
  $out = array();
  $trail = _nodehierarchy_get_node_primary_ancestor_menu_links($nid);
  $trail = (array) array_pop($trail);
  foreach ($trail as $menu_link) {
    $out[] = $menu_link['nid'];
  }
  return $out;
}

/**
 * Helper function to load a menu_link given a mlid.
 */
function _nodehierarchy_load_menu_link($mlid) {
  if ($mlid) {
    $item = db_query("SELECT *, parent.nid as pnid FROM {menu_links} ml LEFT JOIN {nodehierarchy_menu_links} parent on parent.mlid = ml.plid WHERE ml.module = :module AND ml.mlid = :mlid", array(':module' => 'nodehierarchy', ':mlid' => $mlid))->fetchAssoc();
    return _nodehierarchy_prepare_menu_link($item);
  }
  return NULL;
}

/**
 * Helper function to prepare a menu link after it's been loaded.
 */
function _nodehierarchy_prepare_menu_link($menu_link) {
  if ($menu_link) {
    $menu_link['options'] = is_string($menu_link['options']) ? unserialize($menu_link['options']) : $menu_link['options'];
    $menu_link['enabled'] = !$menu_link['hidden'];
    list(, $menu_link['nid']) = explode('/', $menu_link['link_path']);
  }
  return $menu_link;
}

/**
 * Get the next child weight for a given plid.
 */
function _nodehierarchy_get_menu_link_next_child_weight($plid) {
  $out = db_query("SELECT MAX(weight) FROM {menu_links} WHERE module = :module AND plid = :plid", array(':module' => 'nodehierarchy', ':plid' => $plid))->fetchField();
  if ($out !== NULL) {
    return $out + 1;
  }
  return 0;
}

/**
 * Get the parent link ID from the given menu link id.
 */
function _nodehierarchy_get_plid_from_mlid($mlid) {
  if ($mlid) {
    return (int) db_query("SELECT plid FROM {menu_links} WHERE mlid = :mlid", array(':mlid' => $mlid))->fetchField();
  }
  return NULL;
}

/**
 * Delete all link from the node to its menu items.
 */
function nodehierarchy_delete_node_nodehierarchy_menu_links($nid) {
  foreach (_nodehierarchy_get_node_mlids($nid) as $mlid) {
    nodehierarchy_delete_node_nodehierarchy_menu_link($mlid);
  }
}

/**
 * Delete a single menu_link from a node.
 */
function nodehierarchy_delete_node_nodehierarchy_menu_link($mlid) {
  menu_link_delete($mlid);
  db_delete('nodehierarchy_menu_links')
  ->condition('mlid', $mlid)
  ->execute();
}


/**
 * Get the default menu link values for a new nodehierarchy menu link.
 */
function _nodehierarchy_default_menu_link($nid = NULL, $plid = 0, $enabled = FALSE) {
  return array(
    'mlid' => NULL,
    'module' => 'nodehierarchy',
    'menu_name' => variable_get('nodehierarchy_default_menu_name', 'navigation'),
    'router_path' => 'node/%',
    'link_path' => !empty($nid) ? 'node/' . $nid : '',
    'hidden' => !$enabled,
    'enabled' => $enabled,
    'plid' => $plid,
    'weight' => 0,
    'nid' => !empty($nid) ? $nid : NULL,
    'customized' => 0,
  );
}

/**
 * Get the menu link for the given node.
 */
function _nodehierarchy_create_node_menu_link($nid) {
  $node = node_load($nid);
  $menu_link = _nodehierarchy_default_menu_link($node->nid);
  $menu_link['link_title'] = $node->title;
  _nodehierarchy_save_menu_link($menu_link);
  return $menu_link;
}

/**
 * Set the breadcrumbs and active menu to reflect the position of the given
 * node in the site hierarchy.
 *
 * @param $node
 *   The current node
 * @param $add_node
 *   Whether we want the current node in the breadcrumb (eg: for the children tab)
 */
function nodehierarchy_set_breadcrumbs($node, $add_node = FALSE) {
  // Place the given node.
  $breadcrumb = array();

  // Get all the possible breadcrumbs for the node.
  $nid = empty($node->nid) ? null : $node->nid;
  $breadcrumbs = nodehierarchy_get_breadcrumbs($nid);

  // There may be multiple breadcrumbs, but we only want one, so pick the first one.
  $breadcrumb = empty($breadcrumbs[0]) ? array() : (array)$breadcrumbs[0];

  // Remove the node itself if it's not needed (we would want it for the children tab for example).
  if (!$add_node) {
    array_pop($breadcrumb);
  }

  // Stick the home link on the top of the breadcrumb.
  array_unshift($breadcrumb, l(t('Home'), '<front>'));

  drupal_set_breadcrumb($breadcrumb);
}

/**
 * Get the breadcrumbs for the given node.
 *
 * There could be multiple breadcrumbs because there could be multiple parents.
 */
function nodehierarchy_get_breadcrumbs($nid) {
  $breadcrumbs = array();

  // Retrieve the descendent list of menu links and convert them to a breadcrumb trail.
  $menu_link_trails = _nodehierarchy_get_node_ancestor_menu_links($nid);
  foreach ($menu_link_trails as $menu_links) {
    $breadcrumb = array();
    foreach ($menu_links as $menu_link) {
      $breadcrumb[] = l($menu_link['link_title'], $menu_link['link_path']);
    }
    $breadcrumbs[] = $breadcrumb;
  }
  return $breadcrumbs;
}

/**
 * Get the menu links of each of a node's ancestors.
 */
function _nodehierarchy_get_node_ancestor_menu_links($nid) {
  $menu_links = _nodehierarchy_get_node_menu_links($nid);
  $out = array();
  foreach ($menu_links as $menu_link) {
    $out[$menu_link['mlid']] = array();
    for ($i = 1; $i < MENU_MAX_DEPTH; $i++) {
      if ($plid = $menu_link['p' . $i]) {
        $out[$menu_link['mlid']][] = _nodehierarchy_load_menu_link($plid);
      }
    }
  }
  return $out;
}

/**
 * Get the the primary ancestor chain for a node.
 */
function _nodehierarchy_get_node_primary_ancestor_menu_links($nid) {
  $out = array();
  if ($menu_link = _nodehierarchy_get_node_menu_link($nid)) {
    for ($i = 1; $i < MENU_MAX_DEPTH; $i++) {
      if ($plid = $menu_link['p' . $i]) {
        $out[$menu_link['mlid']][] = _nodehierarchy_load_menu_link($plid);
      }
    }
  }
  return $out;
}

/**
 * Get the parent selector pulldown.
 */
function _nodehierarchy_get_parent_selector($child_type, $parent, $exclude = NULL) {
  // Allow other modules to create the pulldown first.
  // Modules implementing this hook, should return the form element inside an array with a numeric index.
  // This prevents module_invoke_all from merging the outputs to make an invalid form array.
  $out = module_invoke_all('nodehierarchy_get_parent_selector', $child_type, $parent, $exclude);

  if ($out) {
    // Return the last element defined (any others are thrown away);
    return end($out);
  }

  $default_value = _nodehierarchy_get_parent_selector_value($parent);

  // If no other modules defined the pulldown, then define it here.
  $options = array(0 => '-- ' . t('NONE') . ' --');
  $items = _nodehierarchy_parent_options($child_type, $exclude);

  foreach ($items as $key => $item) {
    $options[$key] = _nodehierarchy_parent_option_title($item);
  }

  // Make sure the current value is enabled so items can be resaved.
  $items[$default_value]['disabled'] = 0;

  $out = array(
    '#type' => 'select',
    '#title' => t('Parent Node'),
    '#default_value' => $default_value,
    '#attributes' => array('class' => array('nodehierarchy-parent-selector')),
    '#options' => $options,
    '#items' => $items,
    '#theme' => 'nodehierarchy_parent_selector',
    '#element_validate' => array('nodehierarchy_parent_selector_validate', 'nodehierarchy_parent_selector_to_nid'),
  );
  return $out;
}



/**
 * Theme the parent selector pulldown, allowing for disabled options.
 */
function theme_nodehierarchy_parent_selector($variables) {
  $element = $variables['element'];
  element_set_attributes($element, array('id', 'name', 'size'));
  _form_set_class($element, array('form-select'));

  // Assemble the options.
  $options = '';
  foreach ($element['#options'] as $key => $option) {
    $attributes = '';
    // If the option represents a parent item (and not just the none option).
    if (!empty($element['#items'][$key]) && $item = $element['#items'][$key]) {
      if ($element['#value'] == $key) {
        $attributes .= ' selected="selected"';
      }
      if ($item['disabled']) {
        $attributes .= ' disabled="disabled"';
        $option .= '*';
        $element['#description'] = t('Nodes marked with a * cannot be a parent for this node because they are not an allowed parent type.');
        if (user_access('administer hierarchy')) {
          $element['#description'] .= t(' To allow these nodes to be parents of this node, change the setting for that node type in the !settings', array('!settings' => l(t('Node Hierarchy settings'), 'admin/config/nodehierarchy')));
        }
      }
    }
    $options .= '<option value="' . check_plain($key) . '"' . $attributes . '>' . $option . '</option>';
  }
  return '<select' . drupal_attributes($element['#attributes']) . '>' . $options . '</select>';
}

/**
 * Validate the parent node selector to make sure the parent is legal.
 */
function nodehierarchy_parent_selector_validate($element, &$form_state) {
  $selection = @$element['#items'][$element['#value']];
  if (is_array($selection) && !empty($selection['disabled'])) {
    form_error($element, t('You have selected a parent node which cannot be a parent of this node type.'));

    if (!empty($selection['nid']) && !empty($form_state['values']['nid']) && in_array($form_state['values']['nid'], nodehierarchy_get_node_ancestor_nids($selection['nid']))) {
      form_error($element, t('The parent of this node can not be itself or any if its decendants.'));
    }
  }
}

/**
 * An element validator for the parent pulldown selector that retuns a nid for saving.
 */
function nodehierarchy_parent_selector_to_nid($element, &$form_state) {
  $value = $element['#value'];
  if ($value) {
    $nid = $element['#items'][$value]['nid'];
    form_set_value($element, $nid, $form_state);
  }
}

/**
 * Return a list of menu items that are valid possible parents for the given node.
 */
function _nodehierarchy_parent_options($child_type, $exclude = NULL) {
  static $options = array();

  // If these options have already been generated, then return that saved version.
  if (isset($options[$child_type][$exclude])) {
    return $options[$child_type][$exclude];
  }

  // Get all the possible parents.
  $types = nodehierarchy_get_allowed_parent_types();
  foreach ($types as $i => $type) {
    $types[$i] = "'$type'";
  }

  // Get the items with menu links.
  $result = $items = $mlids = array();
  if ($types) {
    $result = db_query(
                        "SELECT n.nid, n.type as type, n.title as title, n.uid as uid, ml.*, IF(depth IS NULL, 1, depth) as depth, IF(ml.mlid IS NULL, CONCAT('nid:', n.nid), ml.mlid) as mlid, ml.mlid as linkid
                           FROM {node} n
                      LEFT JOIN {nodehierarchy_menu_links} nh_parent
                             ON nh_parent.nid = n.nid
                      LEFT JOIN {menu_links} ml
                             ON ml.mlid = nh_parent.mlid
                          WHERE (ml.module = 'nodehierarchy' OR ml.module IS NULL)
                            AND n.type IN (" . implode(', ', $types) . ")
                          ORDER BY IF(p1 IS NULL, n.created, 0) ASC, p1 ASC, p2 ASC, p3 ASC, p4 ASC, p5 ASC, p6 ASC, p7 ASC, p8 ASC, p9 ASC",
                        array(),
                        array('fetch' => PDO::FETCH_ASSOC)
                    );
  }

  // Flatten tree to a list of options.
  $parent_types = nodehierarchy_get_allowed_parent_types($child_type);
  $out = nodehierarchy_tree_data($result, $exclude, $parent_types);

  // Static caching to prevent these options being built more than once.
  $options[$child_type][$exclude] = $out;
  return $out;
}

/**
 * Build the data representing a menu tree.
 *
 * @param $result
 *   The database result.
 * @param $parents
 *   An array of the plid values that represent the path from the current page
 *   to the root of the menu tree.
 * @param $depth
 *   The depth of the current menu tree.
 * @return
 *   See menu_tree_page_data for a description of the data structure.
 */
function nodehierarchy_tree_data($result = NULL, $exclude = NULL, $allowed_types, $depth = 1) {
  list(, $tree) = _nodehierarchy_tree_data($result, $exclude, $allowed_types, $depth);
  return $tree;
}

/**
 * Recursive helper function to build the data representing a menu tree.
 *
 * The function is a bit complex because the rendering of an item depends on
 * the next menu item. So we are always rendering the element previously
 * processed not the current one.
 */
function _nodehierarchy_tree_data($result, $exclude = NULL, $allowed_types, $depth, $previous_element = array()) {
  $remnant = NULL;
  $tree = array();
  $enabled_tree = TRUE;
  $exclude = NULL;

  foreach ($result as $item) {
    if ($exclude !== $item['nid']) {
      $item['disabled'] = in_array($item['type'], $allowed_types) ? FALSE : TRUE;
//      $item['disabled'] = $item['disabled'] || (!node_access('update', $item) && !user_access('create child of any parent'));
      $enabled_tree = $enabled_tree || empty($item['disabled']) || (isset($previous_element['disabled']) && empty($previous_element['disabled']));

      // The current item is the first in a new submenu.
      if ($item['depth'] > $depth) {
        // _menu_tree returns an item and the menu tree structure.
        list($item, $below) = _nodehierarchy_tree_data($result, $exclude, $allowed_types, $item['depth'], $item);
        if ($previous_element && ($below || !$previous_element['disabled'])) {
          $tree[_nodehierarchy_get_parent_selector_value($previous_element)] = $previous_element;
        }
        $tree += $below;

        // We need to fall back one level.
        if (!isset($item) || $item['depth'] < $depth) {
          return $enabled_tree ? array($item, $tree) : array($item, array());
        }
        // This will be the link to be output in the next iteration.
        $previous_element = $item;
      }
      // We are at the same depth, so we use the previous element.
      elseif ($item['depth'] == $depth) {
        if ($previous_element && !$previous_element['disabled']) {
          // Only the first time.
          $tree[_nodehierarchy_get_parent_selector_value($previous_element)] = $previous_element;
        }
        // This will be the link to be output in the next iteration.
        $previous_element = $item;
      }
      // The submenu ended with the previous item, so pass back the current item.
      else {
        $remnant = $item;
        break;
      }
    }
  }
  if ($previous_element && !$previous_element['disabled']) {
    // We have one more link dangling.
    $tree[_nodehierarchy_get_parent_selector_value($previous_element)] = $previous_element;
  }
  return $enabled_tree ? array($remnant, $tree) : array($remnant, array());
}

/**
 * Get the title of the given item to display in a pulldown.
 */
function _nodehierarchy_parent_option_title($item) {
  return str_repeat('--', $item['depth'] -1) . ' ' . truncate_utf8($item['title'], 60, TRUE, FALSE);
}

/**
 * Get the parent selector key from a menu_link array. Returns either nid:mlid or nid
 */
function _nodehierarchy_get_parent_selector_value($parent) {
  $out = 0;
  // If the parent value is a node ID, laod the menu_link for that node.
  if (is_numeric($parent)) {
    $out = trim($parent . ':' . _nodehierarchy_get_node_mlid($parent), ':');
  }
  elseif (is_numeric($parent['mlid'])) {
    $out = $parent['nid'] . ':' . $parent['mlid'];
  }
  elseif (!empty($parent['nid'])) {
    $out = $parent['nid'];
  }
  return $out;
}

/**
 * Display links to create new children nodes of the given node
 */
function nodehierarchy_new_child_links($node) {
  return theme('nodehierarchy_new_child_links', array('node' => $node));
}

/**
 * Display links to create new children nodes of the given node
 */
function theme_nodehierarchy_new_child_links($variables) {
  $node = $variables['node'];
  $out = array();
  $create_links = array();

  if (user_access('create child nodes') && (user_access('create child of any parent') || node_access('update', $node))) {
    foreach (nodehierarchy_get_allowed_child_types($node->type) as $key) {
      if (node_access('create', $key)) {
        $type_name = node_type_get_name($key);
        $destination = (array)drupal_get_destination() + array('parent' => $node->nid);
        $key = str_replace('_', '-', $key);
        $title = t('Add a new %s.', array('%s' => $type_name));
        $create_links[] = l($type_name, "node/add/$key", array('query' => $destination, 'attributes' => array('title' => $title)));
      }
    }
    if ($create_links) {
      $out[] = array('#children' => '<div class="newchild">' . t("Create new child !s", array('!s' => implode(" | ", $create_links))) . '</div>');
    }
  }
  return $out;
}
