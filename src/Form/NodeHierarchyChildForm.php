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
    $hierarchy_storage = \Drupal::service('nodehierarchy.outline_storage');
    $hierarchy_manager = \Drupal::service('nodehierarchy.manager');

    $url = \Drupal\Core\Url::fromRoute('<current>');
    $curr_path = $url->toString();
    $path = explode('/', $curr_path);
    $nid = $path[2];
    $node = Node::load($nid);
    $children = $hierarchy_storage->hierarchyGetNodeChildren($node);

//    dsm($children);

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
        $child_item['node'] = array(
          '#type' => 'value',
          '#value' => $node,
        );
        $url = Url::fromRoute('entity.node.canonical', array('node'=>$node->id()));
//        dsm($url);
        $child_item['title']      = array('#type' => 'markup', '#markup' => $this->l($node->getTitle(), $url));
        $child_item['type']       = array('#type' => 'markup', '#markup' => $type_names[$node->getType()]);

        $cweight = $form_state->getValue($child->nhid['cweight']);
        $child_item['cweight']    = array(
          '#type' => 'weight',
          '#delta' => $delta,
          '#default_value' => isset($cweight) ? $cweight : $child->cweight,
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

    // TODO: add using renderable array instead, then find suitable place for code
    $current_user = \Drupal::currentUser();
    if ($current_user->hasPermission('create child nodes') && ($current_user->hasPermission('create child of any parent')
        || $node->access('update'))) {

      // Todo: question: are we getting the correct values back from hierarchyGetAllowedChildTypes? Doesn't seem so...
      $url = Url::fromRoute('<current>');
      $curr_path = $url->toString();
      $path = explode('/', $curr_path);
      $nid = $path[2];
      $node = Node::load($nid);
      if (is_object($node)) {
        $node_type = $node->getType();
      }
      $allowed_child_types = $hierarchy_manager->hierarchyGetAllowedChildTypes($node_type);
      foreach ($allowed_child_types as $type) {
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

