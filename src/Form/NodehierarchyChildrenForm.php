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
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\SafeMarkup;


/**
 * Defines a form that is displayed when visiting /node/{node}/children
 */
class NodeHierarchyChildrenForm extends ContentEntityForm {

  /**
   * The hierarchy being displayed.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $entity;

  /**
   * HierarchyManager service.
   *
   * @var \Drupal\nodehierarchy\HierarchyManagerInterface
   */
  protected $hierarchyManager;

  /**
   * Constructs a NodehierarchyChildrenForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface   $entity_manager
   *   The entity manager.
   *
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    parent::__construct($entity_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    // Don't show a parent form here
    return NULL;
  }

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
        '#header' => array(t('Child Title'), t('Type'), t('Weight') , t('Operations')),
        '#tabledrag' => array(
          array(
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'children-order-weight',
          )),
      );
      // Add CSS to the form via .libraries.yml file
      $form['#attached'] = array(
        'library' => array('nodehierarchy/nodehierarchy.children'),
      );
    }
    $type_names = node_type_get_names();

    foreach ($children as $child) {
      if ($node = Node::load($child->cnid)) {
        $url = Url::fromRoute('entity.node.canonical', array('node'=>$node->id()));
        $form['children'][$child->hid]['#attributes']['class'][] = 'draggable';
        $form['children'][$child->hid]['#weight'] = $child->cweight;
        $form['children'][$child->hid]['title'] = array(
          '#markup' => $this->l(SafeMarkup::checkPlain($node->getTitle()), $url),
        );
        $form['children'][$child->hid]['type'] = array(
          '#markup' => SafeMarkup::checkPlain($type_names[$node->getType()]),
        );
        //
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
        // The link to the child tab
        $form['children'][$child->hid]['operations']['#links']['children'] = array(
          'title' => t('Children'),
          'url' => Url::fromRoute('entity.node.nodehierarchy_children_form', array('node'=>$node->id())),
        );
      }
    }

    if (!is_array($form['children'])){
      // Todo: better use the #empty instead; see https://www.drupal.org/node/1876710
      $form['no_children'] = array('#type' => 'markup', '#markup' => t('This node has no children.'));
    }

    // Build the add child links
    // TODO: add using renderable array instead, then find suitable place for code
    $current_user = \Drupal::currentUser();
    if ($current_user->hasPermission('create child nodes') && ($current_user->hasPermission('create child of any parent')
        || $node->access('update'))) {

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
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Update child order');
    $actions['delete']['#value'] = $this->t('Remove all children');
//    $actions['delete']['#access'] = $this->bookManager->checkNodeIsRemovable($this->entity);
    // Don't show the actions links if there are no children
    if ($form['no_children']) {
      unset ($actions['submit']);
      unset ($actions['delete']);
    }
    return $actions;
  }

  /**
   * {@inheritdoc}
   *
   * Here we are loading the weights/IDs from the values set using the tabledrag
   * in the form() function above.
   *
   * We then create an object containing only the child weight and hierarchy ID,
   * and write that to the database.
   *
   * We don't do any checking because no Save button will be shown if no
   * children are present.
   */
  public function save(array $form, FormStateInterface $form_state) {
    $children = $form_state->getValue('children');
    $hierarchyManager = \Drupal::service('nodehierarchy.manager');
    foreach ($children as $hid => $child) {
      $item->hid = $hid;
      $item->cweight = $child['weight'];
      $hierarchyManager->updateHierarchy($item);
    }
  }

}

