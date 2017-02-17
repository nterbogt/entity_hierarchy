<?php

namespace Drupal\entity_hierarchy\Form;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory;
use Drupal\entity_hierarchy\Storage\NestedSetStorageFactory;
use Drupal\Core\Entity\ContentEntityForm;
use PNX\NestedSet\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a form for re-ordering children.
 */
class HierarchyChildrenForm extends ContentEntityForm {
  const CHILD_ENTITIES_STORAGE = 'child_entities';

  /**
   * The hierarchy being displayed.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * Nested set storage factory.
   *
   * @var \Drupal\entity_hierarchy\Storage\NestedSetStorageFactory
   */
  protected $nestedSetStorageFactory;

  /**
   * Nested set node key factory.
   *
   * @var \Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory
   */
  protected $nodeKeyFactory;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a new HierarchyChildrenForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   Entity type manager.
   * @param \Drupal\entity_hierarchy\Storage\NestedSetStorageFactory $nestedSetStorageFactory
   *   Nested set storage.
   * @param \Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory $nodeKeyFactory
   *   Node key factory.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity field manager.
   */
  public function __construct($entity_manager, NestedSetStorageFactory $nestedSetStorageFactory, NestedSetNodeKeyFactory $nodeKeyFactory, EntityFieldManagerInterface $entityFieldManager) {
    parent::__construct($entity_manager);
    $this->nestedSetStorageFactory = $nestedSetStorageFactory;
    $this->nodeKeyFactory = $nodeKeyFactory;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('entity_hierarchy.nested_set_storage_factory'),
      $container->get('entity_hierarchy.nested_set_node_factory'),
      $container->get('entity_field.manager')
    );
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
    $cache = (new CacheableMetadata())->addCacheableDependency($this->entity);

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $fields */
    $fields = array_filter($this->entityFieldManager->getFieldDefinitions($this->entity->getEntityTypeId(), $this->entity->bundle()), function (FieldDefinitionInterface $field) {
      return $field->getType() === 'entity_reference_hierarchy';
    });
    if (!$fields) {
      throw new NotFoundHttpException();
    }
    $fieldName = $form_state->getValue('fieldname') ?: reset($fields)->getName();
    if (count($fields) === 1) {
      $form['fieldname'] = [
        '#type' => 'value',
        '#value' => $fieldName,
      ];
    }
    else {
      $form['select_field'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['container-inline'],
        ],
      ];
      $form['select_field']['fieldname'] = [
        '#type' => 'select',
        '#title' => $this->t('Field'),
        '#description' => $this->t('Field to reorder children in.'),
        '#options' => array_map(function (FieldDefinitionInterface $field) {
          return $this->entity->getFieldDefinitions()[$field->getName()]->getLabel();
      }, $fields),
        '#default_value' => $fieldName,
      ];
      $form['select_field']['update'] = [
        '#type' => 'submit',
        '#value' => $this->t('Update'),
        '#submit' => ['::updateField']
      ];
    }
    /** @var \PNX\NestedSet\Node[] $children */
    /** @var \PNX\NestedSet\NestedSetInterface $storage */
    $storage = $this->nestedSetStorageFactory->get($fieldName, $this->entity->getEntityTypeId());
    $children = $storage->findChildren($this->nodeKeyFactory->fromEntity($this->entity));
    $childEntities = $this->loadAndAccessCheckEntitysForTreeNodes($children, $cache);
    $form_state->setTemporaryValue(self::CHILD_ENTITIES_STORAGE, $childEntities);
    $form['#attached']['library'][] = 'entity_hierarchy/entity_hierarchy.nodetypeform';
    $form['children'] = [
      '#type' => 'table',
      '#header' => [t('Child'), t('Weight') , t('Operations')],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'children-order-weight',
        ]
      ],
      '#empty' => $this->t('There are no children to reorder'),
    ];

    foreach ($children as $weight => $node) {
      if (!$childEntities->contains($node)) {
        // Doesn't exist or is access hidden.
        continue;
      }
      /** @var \Drupal\Core\Entity\ContentEntityInterface $childEntity */
      $childEntity = $childEntities->offsetGet($node);
      $child = $node->getId();
      $form['children'][$child]['#attributes']['class'][] = 'draggable';
      $form['children'][$child]['#weight'] = $weight;
      $form['children'][$child]['title'] = $childEntity->toLink()->toRenderable();
      $form['children'][$child]['weight'] = [
        '#type' => 'weight',
        '#delta' => 50,
        '#title' => t('Weight for @title', ['@title' => $childEntity->label()]),
        '#title_display' => 'invisible',
        '#default_value' => $childEntity->{$fieldName}->weight,
        // Classify the weight element for #tabledrag.
        '#attributes' => ['class' => ['children-order-weight']],
      ];
      // Operations column.
      $form['children'][$child]['operations'] = [
        '#type' => 'operations',
        '#links' => [],
      ];
      if ($childEntity->access('update') && $childEntity->hasLinkTemplate('edit-form')) {
        $form['children'][$child]['operations']['#links']['edit'] = [
          'title' => t('Edit'),
          'url' => $childEntity->toUrl('edit-form'),
        ];
      }
      if ($childEntity->access('delete') && $childEntity->hasLinkTemplate('delete-form')) {
        $form['children'][$child]['operations']['#links']['delete'] = [
          'title' => t('Delete'),
          'url' => $childEntity->toUrl('delete-form'),
        ];
      }
    }

    $cache->applyTo($form);
    return $form;
  }

  /**
   * Submit handler for update field button.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Form state.
   */
  public function updateField(array $form, FormStateInterface $formState) {
    $formState->setRebuild(TRUE);
  }

  /**
   * Loads Drupal node entities for given tree nodes and checks access.
   *
   * @param \PNX\NestedSet\Node[] $nodes
   *   Tree node to load entity for.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cache
   *   Cache metadata.
   *
   * @return \SplObjectStorage
   *   Map of entities keyed by node.
   */
  protected function loadAndAccessCheckEntitysForTreeNodes(array $nodes, RefinableCacheableDependencyInterface $cache) {
    $entities = $this->entityTypeManager->getStorage($this->entity->getEntityTypeId())->loadMultiple(array_map(function (Node $node) {
      return $node->getId();
    }, $nodes));
    $loadedEntities = new \SplObjectStorage();
    foreach ($nodes as $node) {
      $nodeId = $node->getId();
      $entity = $entities[$nodeId] ?? FALSE;
      if (!$entity || !$entity->access('view label')) {
        continue;
      }
      $loadedEntities[$node] = $entity;
      $cache->addCacheableDependency($entity);
    }
    return $loadedEntities;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Update child order');
    unset ($actions['delete']);
    // Don't show the actions links if there are no children
    if (isset($form['no_children'])) {
      unset ($actions['submit']);
    }
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $children = $form_state->getValue('children');
    $childEntities = $form_state->getTemporaryValue(self::CHILD_ENTITIES_STORAGE);
    $fieldName = $form_state->getValue('fieldname');
    foreach ($childEntities as $node) {
      $childEntity = $childEntities->offsetGet($node);
      $childEntity->{$fieldName}->weight = $children[$node->getId()]['weight'];
      $childEntity->save();
    }
    drupal_set_message($this->t('Updated child order.'));
  }

}

