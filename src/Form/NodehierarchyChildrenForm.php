<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\Form\NodehierarchyChildrenForm.
 */

namespace Drupal\nodehierarchy\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\Core\Render\Element;
use Drupal\Component\Utility\String;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form for Node Hierarchy Admin settings.
 */
class NodeHierarchyChildrenForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $hierarchy_storage = \Drupal::service('nodehierarchy.outline_storage');
    $hierarchy_manager = \Drupal::service('nodehierarchy.manager');

    $children = $this->entity;
    $id = $this->entity->id();

    $url = Url::fromRoute('<current>');
    $curr_path = $url->toString();
    $path = explode('/', $curr_path);
    $nid = $path[2];
    $node = Node::load($nid);
    $children = $hierarchy_storage->hierarchyGetNodeChildren($node);

    if ($children) {
      $form['children'] = array(
        '#type' => 'table',
        '#header' => array(t('Title'), t('Type'), t('Weight') , t('Operations')),
        '#tabledrag' => array(
          array(
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'children-order-weight',
          )),
      );
    }
    $type_names = node_type_get_names();

    foreach ($children as $child) {
      if ($node = Node::load($child->cnid)) {
        $url = Url::fromRoute('entity.node.canonical', array('node'=>$node->id()));
        $form['children'][$child->hid]['#attributes']['class'][] = 'draggable';
        $form['children'][$child->hid]['#weight'] = $child->cweight;
        $form['children'][$child->hid]['title'] = array(
          '#markup' => $this->l(String::checkPlain($node->getTitle()), $url),
        );
        $form['children'][$child->hid]['type'] = array(
          '#markup' => String::checkPlain($type_names[$node->getType()]),
        );
        // TableDrag: Weight column element.
        $form['children'][$child->hid]['weight'] = array(
          '#type' => 'weight',
          '#title' => t('Weight for @title', array('@title' => $this->l($node->getTitle(), $url))),
          '#title_display' => 'invisible',
          '#default_value' => $child->cweight,
          // Classify the weight element for #tabledrag.
          '#attributes' => array('class' => array('children-order-weight')),
        );
        // Operations column.
        $form['children'][$child->hid]['operations'] = array(
          '#type' => 'operations',
          '#links' => array(),
        );
        $id = $node->id();
        $form['children'][$child->hid]['operations']['#links']['edit'] = array(
          'title' => t('Edit'),
          'url' => Url::fromRoute('entity.node.edit_form', array('node'=>$node->id())),
        );
        $form['children'][$child->hid]['operations']['#links']['delete'] = array(
          'title' => t('Delete'),
          'url' => Url::fromRoute('entity.node.delete_form', array('node'=>$node->id())),
        );
//        $form['children'][$child->hid]['operations']['#links']['children'] = array(
//          'title' => t('Children'),
//          'url' => Url::fromRoute('nodehierarchy.nodehierarchy_node_load', array('node'=>$node->id())),
//        );
      }
    }

    if (is_array($form['children'])){
      if (Element::children($form['children'])) {
        $form['submit'] = array(
          '#type' => 'submit',
          '#value' => t('Save child order'),
        );
      }
    }
    else {
      $form['no_children'] = array('#type' => 'markup', '#markup' => t('This node has no children.'));
    }


    // Build the add child links
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
    return parent::form($form, $form_state);
  }

//  /**
//   * {@inheritdoc}
//   */
//  public function submitForm(array &$form, FormStateInterface $form_state) {
//    $values = $form_state->getValues();
//    $config = $this->config('nodehierarchy.child.settings');
//    $config->set('children', $values['children']);
//    $config->save();
//
//    parent::submitForm($form, $form_state);
//  }

//  /**
//   * {@inheritdoc}
//   */
//  protected function getEditableConfigNames() {
//    return ['nodehierarchy.child.settings'];
//  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $hierarchy = $this->entity;
    $hierarchy->save();
  }

}

