<?php

/**
 * @file
 * Contains \Drupal\entity_hierarchy\Form\NodehierarchyChildrenForm.
 */

namespace Drupal\entity_hierarchy\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_hierarchy\HierarchyBase;
use Drupal\node\Entity\Node;
use Drupal\Core\Render\Element;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Link;


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
   * @var \Drupal\entity_hierarchy\HierarchyManagerInterface
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
    $hierarchy_manager = \Drupal::service('entity_hierarchy.manager');

    $id = $this->entity->id();
    $children = $hierarchy_manager->hierarchyLoadAllChildren($id);

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
        'library' => array('entity_hierarchy/entity_hierarchy.children'),
      );
    }
    $type_names = node_type_get_names();

    foreach ($children as $weight => $child) {
      if ($node = Node::load($child)) {
        $url = Url::fromRoute('entity.node.canonical', array('node'=>$node->id()));
        $form['children'][$child]['#attributes']['class'][] = 'draggable';
        $form['children'][$child]['#weight'] = $weight;
        $form['children'][$child]['title'] = array(
          '#markup' => $this->l(SafeMarkup::checkPlain($node->getTitle()), $url),
        );
        $form['children'][$child]['type'] = array(
          '#markup' => SafeMarkup::checkPlain($type_names[$node->getType()]),
        );
        //
        $form['children'][$child]['weight'] = array(
          '#type' => 'weight',
          '#title' => t('Weight for @title', array('@title' => $this->l($node->getTitle(), $url))),
          '#title_display' => 'invisible',
          '#default_value' => $weight,
          // Classify the weight element for #tabledrag.
          '#attributes' => array('class' => array('children-order-weight')),
        );
        // Operations column.
        $form['children'][$child]['operations'] = array(
          '#type' => 'operations',
          '#links' => array(),
        );
        $form['children'][$child]['operations']['#links']['edit'] = array(
          'title' => t('Edit'),
          'url' => Url::fromRoute('entity.node.edit_form', array('node'=>$node->id())),
        );
        $form['children'][$child]['operations']['#links']['delete'] = array(
          'title' => t('Delete'),
          'url' => Url::fromRoute('entity.node.delete_form', array('node'=>$node->id())),
        );
        // The link to the child tab
        $form['children'][$child]['operations']['#links']['children'] = array(
          'title' => t('Children'),
          'url' => Url::fromRoute('entity.node.entity_hierarchy_children_form', array('node'=>$node->id())),
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
      foreach ($hierarchy_manager->hierarchyGetAllowedChildTypes($node->getType()) as $key => $type) {
        if ($node->access('create')) {
          $destination = (array) drupal_get_destination() + array('parent' => $nid);
          $url = Url::fromRoute('node.add', array('node_type' => $key), array('query' => $destination));
          $link = Link::fromTextAndUrl(t($type), $url);
          $create_links[] = render(@$link->toRenderable());
        }
        if ($create_links) {
          $out = '<div class="newchild">' . t('Create new child ') . implode(" | ", $create_links) . '</div>';
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
    // Don't show the actions links if there are no children
    if (isset($form['no_children'])) {
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
    $hierarchyManager = \Drupal::service('entity_hierarchy.manager');
    $id = $this->entity->id();
    $hierarchy = new HierarchyBase($id);
    foreach ($children as $hid => $child) {
      $hierarchyManager->hierarchyUpdateWeight($hid, $child['weight']);
    }
  }

}

