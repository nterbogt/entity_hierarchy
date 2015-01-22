<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\Form\NodeHierarchyChildForm.
 */

namespace Drupal\nodehierarchy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
//use Drupal\node\NodeInterface;
use Drupal\Core\Url;
use \Drupal\node\Entity\Node;

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
    $config = $this->config('nodehierarchy.child.settings');

    // Load the node object associated with this form
    $url = \Drupal\Core\Url::fromRoute('<current>');
    $curr_path = $url->toString();
    $path = explode('/', $curr_path);
    $nid = $path[2];

    $node = Node::load($path[2]);
    $node_type = $node->getType();

    $children_links = _nodehierarchy_get_children_menu_links($nid, FALSE);
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
      unset($form['submit']);
    }

    // TODO: add using theme_ function like D7
    if (\Drupal::currentUser()->hasPermission('create child nodes')
      && (\Drupal::currentUser()->hasPermission('create child of any parent')
      || $node->access('update'))) {

      foreach (nodehierarchy_get_allowed_child_types($node_type) as $key) {
        if ($node->access('create')) {
          $destination = (array) drupal_get_destination() + array('parent' => $nid);
          //$key = str_replace('_', '-', $key);
          //$title = t('Add a new %s.', array('%s' => $type_name));

          //$create_links[] = l($type_name, "node/add/$key", array('query' => $destination, 'attributes' => array('title' => $title)));
          $url = Url::fromRoute('node.add', array('node_type' => $node_type), array('query' => $destination));
          $create_links[] = $this->l($node_type, $url);

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
    // dpm($values);

    $config = $this->config('nodehierarchy.child.settings');
    $config->set('children', $values['children']);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}