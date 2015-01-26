<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\Form\NodeHierarchyChildForm.
 */

namespace Drupal\nodehierarchy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Defines a form for Node Hierarchy Admin settings.
 */
class NodeHierarchyChildForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'nodehierarchy_child_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $url = \Drupal\Core\Url::fromRoute('<current>');
    $curr_path = $url->toString();
    $path = explode('/', $curr_path);
    $nid = $path[2];
    $node = Node::load($path[$nid]);
    $node_type = $node->getType();

    $hierarchy_storage = \Drupal::service('nodehierarchy.outline_storage');
    $hierarchy_manager = \Drupal::service('nodehierarchy.manager');

    $children = $hierarchy_storage->hierarchyGetNodeChildren($node, FALSE);

    $form['children'] = array('#tree' => TRUE);
    $type_names = node_type_get_names();

    // Find the maximum weight.
    $delta = count($children);
    foreach ($children as $child) {
      $delta = max($delta, $child->cweight);
    }

    foreach ($children as $child) {
      if ($node = node_load($child->cnid)) {
        $child_item = array();
        $child_item['child']  = array(
          '#type' => 'value',
          '#value' => $child,
        );
        $child_item['node']       = array(
          '#type' => 'value',
          '#value' => $node,
        );
        $child_item['title']      = array('#type' => 'markup', '#markup' => l($node->title, 'node/' . $node->nid));
        $child_item['type']       = array('#type' => 'markup', '#markup' => $type_names[$node->type]);

        $child_item['cweight']    = array(
          '#type' => 'weight',
          '#delta' => $delta,
          '#default_value' => isset($form_state[$child->nhid]['cweight']) ? $form_state[$child->nhid]['cweight'] : $child->cweight,
        );

        $form['children'][$child->nhid] = $child_item;
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

    // TODO: add using theme_ function like D7
    $current_user = \Drupal::currentUser();
    if ($current_user->hasPermission('create child nodes') && ($current_user->hasPermission('create child of any parent')
        || $node->access('update'))) {

      $allowed_child_types = $hierarchy_manager->hierarchyGetAllowedChildTypes($node_type);
      $all_content_types = array_keys(\Drupal\node\Entity\NodeType::loadMultiple());
      $types_selected = array_intersect($all_content_types, $allowed_child_types);
      foreach ($types_selected as $type) {
        if ($node->access('create')) {
          $destination = (array) drupal_get_destination() + array('parent' => $nid);
          $url = Url::fromRoute('node.add', array('node_type' => $type), array('query' => $destination));
          $create_links[] = $this->l($type, $url);
        }
        if ($create_links) {
          $out = '<div class="newchild">' .
            t("Create new child !s", array('!s' => implode(" | ", $create_links))) . '</div>';
        }
        $form['newchild']['#suffix'] = $out;
      }
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->config('nodehierarchy.child.settings');
    $config->set('children', $values['children']);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}

/**
 * Get the allwed parent types for the given child type.
 */
function nodehierarchy_get_allowed_child_types($parent_type) {
  $config = \Drupal::config('nodehierarchy.settings');
  $child_types = array_filter($config->get('nh_allowchild_' . $parent_type));
  return array_unique($child_types);
}