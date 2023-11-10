<?php

namespace Drupal\entity_hierarchy_workbench_access\Plugin\AccessControlHierarchy;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_hierarchy\Information\AncestryLabelTrait;
use Drupal\entity_hierarchy\Storage\QueryBuilderFactory;
use Drupal\field\FieldConfigInterface;
use Drupal\workbench_access\AccessControlHierarchyBase;
use Drupal\workbench_access\UserSectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a hierarchy based on an entity hierarchy field.
 *
 * @AccessControlHierarchy(
 *   id = "entity_hierarchy",
 *   module = "entity_hierarchy",
 *   deriver = "Drupal\entity_hierarchy_workbench_access\Plugin\Derivative\WorkbenchAccessControlDeriver",
 *   label = @Translation("Entity hierarchy"),
 *   description = @Translation("Uses an entity_hierarchy field as an access control hierarchy.")
 * )
 */
class EntityHierarchy extends AccessControlHierarchyBase implements ContainerFactoryPluginInterface {

  use AncestryLabelTrait;

  const CACHE_ID = 'entity_hierarchy_tree';

  /**
   * Constructs a new EntityHierarchy object.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Definition.
   * @param \Drupal\workbench_access\UserSectionStorageInterface $userSectionStorage
   *   User section storage.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity Field manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache backend service.
   * @param \Drupal\entity_hierarchy\Storage\QueryBuilderFactory $queryBuilderFactory
   *   Query builder factory.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    UserSectionStorageInterface $userSectionStorage,
    ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected CacheBackendInterface $cacheBackend,
    protected QueryBuilderFactory $queryBuilderFactory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $userSectionStorage, $configFactory, $entityTypeManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('workbench_access.user_section_storage'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('cache.entity_hierarchy_workbench_access'),
      $container->get('entity_hierarchy.query_builder_factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'bundles' => [],
      'boolean_fields' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTree() {
    if (isset($this->tree)) {
      return $this->tree;
    }

    if ($data = $this->cacheBackend->get(self::CACHE_ID)) {
      $this->tree = $data->data;
      return $this->tree;
    }

    $entity_type_id = $this->pluginDefinition['entity'];
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $field_name = $this->pluginDefinition['field_name'];
    /** @var \Drupal\entity_hierarchy\Storage\QueryBuilder $query_builder */
    $query_builder = $this->queryBuilderFactory->get($field_name, $entity_type_id);
    $entityStorage = $this->entityTypeManager->getStorage($entity_type_id);
    $query = $entityStorage->getQuery()->accessCheck(FALSE);
    $tree = [];
    $or = $query->orConditionGroup();
    $boolean_fields = $this->configuration['boolean_fields'];
    foreach ($boolean_fields as $boolean_field) {
      $or->condition($boolean_field, 1);
      $tree[$boolean_field] = [];
    }
    if ($boolean_fields) {
      $query->condition($or);
    }
    $valid_ids = $query->execute();
    if (!$valid_ids) {
      return $tree;
    }

    $aggregate = $entityStorage->getAggregateQuery()->accessCheck(FALSE);
    $aggregate->groupBy($entity_type->getKey('label'))
      ->groupBy($entity_type->getKey('id'));
    foreach ($boolean_fields as $boolean_field) {
      $aggregate->groupBy($boolean_field);
    }
    $aggregate->condition($entity_type->getKey('id'), $valid_ids, 'IN');
    $details = $aggregate->execute();
    $boolean_parents = array_combine(array_column($details, $entity_type->getKey('id')), array_map(function ($item) use ($entity_type) {
      // Remove the label/id fields.
      unset($item[$entity_type->getKey('label')], $item[$entity_type->getKey('id')]);
      // Return just the boolean fields that were checked.
      return array_keys(array_filter($item));
    }, $details));
    $tags = $entityStorage->getEntityType()->getListCacheTags();
    foreach ($valid_ids as $id) {
      $entity = $entityStorage->load($id);

      $tags = array_merge($tags, $entity->getCacheTags());
      foreach ($boolean_parents[$id] as $parent) {
        $cid = sprintf('%s:%s', self::CACHE_ID, $id);
        if ($entry = $this->cacheBackend->get($cid)) {
          $tree[$parent][$id] = $entry->data;
          // Cache hit.
          continue;
        }
        $entry_tags = [];
        $entry = [
          'label' => $this->generateEntityLabelWithAncestry($entity, $query_builder, $entry_tags),
          'depth' => $query_builder->findDepth($entity),
          'parents' => [],
          'weight' => 1,
          'description' => '',
        ];
        $tree[$parent][$id] = $entry;
        $this->cacheBackend->set($cid, $entry, Cache::PERMANENT, $entry_tags);
        $tags = array_merge($tags, $entry_tags);
      }
      $entityStorage->resetCache([$id]);
    }
    foreach (array_keys($tree) as $parent) {
      uasort($tree[$parent], function (array $a, array $b) {
        $a_weight = $a['weight'] ?: 0;
        $b_weight = $b['weight'] ?: 0;
        return $a_weight <=> $b_weight;
      });
    }
    $this->tree = $tree;
    $this->cacheBackend->set(self::CACHE_ID, $tree, Cache::PERMANENT, array_unique($tags));
    return $this->tree;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityValues(EntityInterface $entity) {
    $queryBuilder = $this->queryBuilderFactory->get($this->pluginDefinition['field_name'], $this->pluginDefinition['entity']);
    return $queryBuilder->findAncestors($entity)->map(fn ($record) => $record->getId());
  }

  /**
   * {@inheritdoc}
   */
  public function applies($entity_type_id, $bundle) {
    if ($entity_type_id !== $this->pluginDefinition['entity']) {
      return FALSE;
    }
    if (!in_array($bundle, $this->configuration['bundles'], TRUE)) {
      return FALSE;
    }
    $fields = $this->entityFieldManager->getFieldMapByFieldType('entity_reference_hierarchy');
    return isset($fields[$entity_type_id][$this->pluginDefinition['field_name']]);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependent_entities = [];
    $fieldStorage = $this->entityTypeManager->getStorage('field_storage_config');
    $dependent_entities[] = $fieldStorage->load(sprintf('%s.%s', $this->pluginDefinition['entity'], $this->pluginDefinition['field_name']));
    foreach ($this->configuration['boolean_fields'] as $field_name) {
      if ($field = $fieldStorage->load(sprintf('%s.%s', $this->pluginDefinition['entity'], $field_name))) {
        $dependent_entities[] = $field;
      }
    }
    $entityType = $this->entityTypeManager->getDefinition($this->pluginDefinition['entity']);
    if ($bundle = $entityType->getBundleEntityType()) {
      $dependent_entities = array_merge($dependent_entities, $this->entityTypeManager->getStorage($bundle)->loadMultiple($this->configuration['bundles']));
    }
    return array_reduce($dependent_entities, function (array $carry, EntityInterface $entity) {
      $carry[$entity->getConfigDependencyKey()][] = $entity->getConfigDependencyName();
      return $carry;
    }, []);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $entity_type_id = $this->pluginDefinition['entity'];
    $booleans = $this->entityFieldManager->getFieldMapByFieldType('boolean');
    $options = [];
    if (isset($booleans[$entity_type_id])) {
      foreach ($booleans[$entity_type_id] as $field_name => $info) {
        $sample_bundle = reset($info['bundles']);
        $fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $sample_bundle);
        $options[$field_name] = $fields[$field_name]->getLabel();
      }
    }
    $form['boolean_fields'] = [
      '#title' => new TranslatableMarkup('Editorial section flag'),
      '#description' => new TranslatableMarkup('Select the field to use to enable the entity as an editorial section. Not all entities in the tree need to be editorial sections.'),
      '#options' => $options,
      '#type' => 'checkboxes',
      '#default_value' => $this->configuration['boolean_fields'],
    ];
    $entityType = $this->entityTypeManager->getDefinition($entity_type_id);
    if ($bundle = $entityType->getBundleEntityType()) {
      $bundleEntityType = $this->entityTypeManager->getDefinition($bundle);
      $form['bundles'] = [
        '#title' => $bundleEntityType->getPluralLabel(),
        '#description' => new TranslatableMarkup('Select the @label this access scheme applies to.', [
          '@label' => $bundleEntityType->getPluralLabel(),
        ]),
        '#type' => 'checkboxes',
        '#options' => array_map(function (EntityInterface $entity) {
          return $entity->label();
        }, $this->entityTypeManager->getStorage($bundle)->loadMultiple()),
        '#default_value' => $this->configuration['bundles'],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $settings = $form_state->getValues();
    if (!array_filter($settings['boolean_fields'])) {
      $form_state->setErrorByName('boolean_fields', new TranslatableMarkup('You must select at least one boolean field to enable an entity as an editorial section'));
    }
    if (!array_filter($settings['bundles'])) {
      $form_state->setErrorByName('bundles', new TranslatableMarkup('You must select at least one bundle to moderate with this access scheme'));
    }
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $settings = $form_state->getValues();
    $settings['boolean_fields'] = array_values(array_filter($settings['boolean_fields']));
    $settings['bundles'] = array_values(array_filter($settings['bundles']));
    $this->configuration = $settings;
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $fields = array_diff($this->configuration['boolean_fields'], array_reduce($dependencies['config'], function (array $carry, $item) {
      if (!$item instanceof FieldConfigInterface) {
        return $carry;
      }
      if ($item->getTargetEntityTypeId() !== $this->pluginDefinition['entity']) {
        return $carry;
      }
      $carry[] = $item->getName();
      return $carry;
    }, []));
    $entityType = $this->entityTypeManager->getDefinition($this->pluginDefinition['entity']);
    $bundles = [];
    if ($bundle = $entityType->getBundleEntityType()) {
      $bundleType = $this->entityTypeManager->getDefinition($bundle);
      $bundles = array_diff($this->configuration['bundles'], array_reduce($dependencies['config'], function (array $carry, $item) use ($bundleType) {
        if (in_array($bundleType->getClass(), class_parents($item))) {
          return $carry;
        }
        $carry[] = $item->id();
        return $carry;
      }, []));
    }
    $changed = ($fields != $this->configuration['boolean_fields']) || ($bundles != $this->configuration['bundles']);
    $this->configuration['boolean_fields'] = $fields;
    $this->configuration['bundles'] = $bundles;
    return $changed;
  }

}
