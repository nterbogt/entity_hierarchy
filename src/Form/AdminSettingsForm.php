<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\Form\AdminSettingsForm.
 */

namespace Drupal\nodehierarchy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Symfony\Component\Validator\Constraints\False;

use PDO;

/**
 * Defines a form for Node Hierarchy Admin settings.
 */
class AdminSettingsForm extends ConfigFormBase {

  /**
   * The list of node types (content types)
   *
   * @var array
   */
  protected $node_types;

  public function __construct(array $node_types) {
    $this->node_types = node_type_get_names();;
  }
  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'nodehierarchy_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('nodehierarchy.settings');

    // Individual type settings.
    $form['nodehierarchy_types'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Node Type Settings'),
      '#description' => $this->t('Settings for individual node types. These can also be individually set for each content type.'),
                                  //array("!ct" => l(t("Content Types"), "admin/structure/types"))),
    );
    $node_types=$this->node_types;
    foreach ($node_types as $key => $type) {
      // Individual type settings.
      $form['nodehierarchy_types'][$key] = array(
        '#type' => 'details',
        '#title' => $type,
        '#open' => FALSE,
      );
      $form['nodehierarchy_types'][$key] += _nodehierarchy_get_node_type_settings_form($key, TRUE);
    }

    // Menu generation.
    $form['nodehierarchy_menu'] = array(
      '#type' => 'fieldset',
      '#title' => t('Node Hierarchy Menu Generation'),
    );
    $form['nodehierarchy_menu']['nodehierarchy_default_menu_name'] = array(
      '#type' => 'select',
      '#title' => t('Default parent menu'),
      '#options' => array_keys(entity_load_multiple('menu')),
      '#default_value' => $config->get('nodehierarchy_default_menu_name'), //variable_get('nodehierarchy_default_menu_name', 'navigation'),
      '#description' => t('If a menu is created for a node with no parent the new menu item will appear in this menu.'),
    );

    $form['nodehierarchy_menu']['nodehierarchy_menu_module_edit'] = array(
      '#type' => 'checkbox',
      '#title' => t('Always show hidden Node Hierarchy menu items on the menu overview forms.'),
      '#default_value' => $config->get('nodehierarchy_menu_module_edit'), //variable_get('nodehierarchy_menu_module_edit', FALSE),
      '#description' => t('Allow disabled nodehierarchy menu items to be edited with regular menu items in the menu overview screen. Turn this off if large Node Hierarchy menus are causing memory errors on menu edit screens.'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    //dpm($values);
    $config = $this->config('nodehierarchy.settings');
    foreach ($this->node_types as $key => $type){
      $config->set('nh_allowchild_'.$key, $values['nh_allowchild_'.$key]);
      $config->set('nh_createmenu_'.$key, $values['nh_createmenu_'.$key]);
      $config->set('nh_multiple_'.$key, $values['nh_multiple_'.$key]);
      $config->set('nh_defaultparent_'.$key, $values['nh_defaultparent_'.$key]);
      $config->set('nodehierarchy_default_menu_name', $values['nodehierarchy_default_menu_name']);
      $config->set('nodehierarchy_menu_module_edit', $values['nodehierarchy_menu_module_edit']);
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}

/**
 * Get the nodehierarchy setting form for a particular node type.
 */
function _nodehierarchy_get_node_type_settings_form($key, $append_key = FALSE) {
  $config =  \Drupal::config('nodehierarchy.settings');

  $form['nh_allowchild'] = array(
    '#type' => 'checkboxes',
    '#title' => t('Allowed child node types'),
    '#options' => node_type_get_names(),
    '#default_value' => $config->get('nh_allowchild_'.$key),
    '#description' => t('Node types which can be created as child nodes of this node type.'),
  );

  $form['nh_defaultparent'] = _nodehierarchy_get_parent_selector($key, $config->get('nh_defaultparent_'.$key));
  $form['nh_defaultparent']['#title'] = t('Default Parent');

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
    foreach (element_children($form) as $form_key) {
      $form[$form_key . '_' . $key] = $form[$form_key];
      unset($form[$form_key]);
    }
  }
  return $form;
}

/**
 * Get the allowed child types for the given parent.
 */
function nodehierarchy_get_allowed_child_types($parent_type) {
  // TODO: is this function still required? Any reasonn to think we may need array_filter($config->get...)?
  $config =  \Drupal::config('nodehierarchy.settings');
  $child_types = $config->get('nh_allowchild_'.$parent_type);
  return array_unique($child_types);
}

/**
 * Get the parent selector pulldown.
 */
function _nodehierarchy_get_parent_selector($child_type, $parent, $exclude = NULL) {
  // Allow other modules to create the pulldown first.
  // Modules implementing this hook, should return the form element inside an array with a numeric index.
  // This prevents module_invoke_all from merging the outputs to make an invalid form array.
  $out = \Drupal::moduleHandler()->invokeAll('nodehierarchy_get_parent_selector', $child_type, $parent, $exclude);
  //$out = module_invoke_all('nodehierarchy_get_parent_selector', $child_type, $parent, $exclude);

  if ($out) {
    // Return the last element defined (any others are thrown away);
    return end($out);
  }

  $default_value = _nodehierarchy_get_parent_selector_value($parent);
  //dpm('parent: '. $parent);
  //dpm('default value: '.$default_value);

  // If no other modules defined the pulldown, then define it here.
  $options = array(0 => '-- ' . t('NONE') . ' --');
  $items = _nodehierarchy_parent_options($child_type, $exclude);

  foreach ($items as $key => $item) {
    $options[$key] = _nodehierarchy_parent_option_title($item);
  }

  // Make sure the current value is enabled so items can be re-saved.
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
//  if ($types) {
//    $result = db_query(
//      "SELECT n.nid, n.type as type, n.title as title, n.uid as uid, ml.*, IF(depth IS NULL, 1, depth) as depth, IF(ml.mlid IS NULL, CONCAT('nid:', n.nid), ml.mlid) as mlid, ml.mlid as linkid
//                           FROM {node} n
//                      LEFT JOIN {nodehierarchy_menu_links} nh_parent
//                             ON nh_parent.nid = n.nid
//                      LEFT JOIN {menu_links} ml
//                             ON ml.mlid = nh_parent.mlid
//                          WHERE (ml.module = 'nodehierarchy' OR ml.module IS NULL)
//                            AND n.type IN (" . implode(', ', $types) . ")
//                          ORDER BY IF(p1 IS NULL, n.created, 0) ASC, p1 ASC, p2 ASC, p3 ASC, p4 ASC, p5 ASC, p6 ASC, p7 ASC, p8 ASC, p9 ASC",
//      array(),
//      array('fetch' => PDO::FETCH_ASSOC)
//    );
//  }

  // Flatten tree to a list of options.
  $parent_types = nodehierarchy_get_allowed_parent_types($child_type);
  $out = nodehierarchy_tree_data($result, $exclude, $parent_types);

  // Static caching to prevent these options being built more than once.
  $options[$child_type][$exclude] = $out;
  return $out;
}

/**
 * Get the allwed parent types for the given child type.
 */
function nodehierarchy_get_allowed_parent_types($child_type = NULL) {
  static $allowed_types = array();
  $config =  \Drupal::config('nodehierarchy.settings');

  // Static cache the results because this may be called many times for the same type on the menu overview screen.
  if (!isset($alowed_types[$child_type])) {
    $parent_types = array();
    foreach (node_type_get_types() as $type => $info) {

      $allowed_children = array_filter($config->get('nh_allowchild_' . $type, array()));
      if ((empty($child_type) && !empty($allowed_children)) || (in_array($child_type, (array) $allowed_children, TRUE))) {
        $parent_types[] = $type;
      }
    }
    $allowed_types[$child_type] = array_unique($parent_types);
    return $allowed_types[$child_type];
  }
  return $allowed_types;
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
    //$out = db_query("SELECT mlid FROM {menu_links} WHERE module = :module AND link_path = :link_path ORDER BY mlid LIMIT 1", array(':module' => 'nodehierarchy', ':link_path' => 'node/' . $nid))->fetchField();

    // Create a new menu item if needed.
    if ($create && !$out) {
      $menu_link = _nodehierarchy_create_node_menu_link($nid);
      $out = $menu_link['mlid'];
    }
  }
  return $out;
}

