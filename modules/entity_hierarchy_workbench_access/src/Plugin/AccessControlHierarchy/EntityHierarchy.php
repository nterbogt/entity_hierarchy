<?php

namespace Drupal\entity_hierarchy_workbench_access\Plugin\AccessControlHierarchy;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_hierarchy\Information\AncestryLabelTrait;
use Drupal\entity_hierarchy\Storage\EntityTreeNodeMapperInterface;
use Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory;
use Drupal\entity_hierarchy\Storage\NestedSetStorageFactory;
use Drupal\workbench_access\AccessControlHierarchyBase;
use Drupal\workbench_access\UserSectionStorageInterface;
use Drupal\workbench_access\WorkbenchAccessManagerInterface;
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

  /**
   * Entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Storage factory.
   *
   * @var \Drupal\entity_hierarchy\Storage\NestedSetStorageFactory
   */
  protected $nestedSetStorageFactory;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity Field manager.
   * @param \Drupal\entity_hierarchy\Storage\NestedSetStorageFactory $nestedSetStorageFactory
   *   Storage factory.
   * @param \Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory $nodeKeyFactory
   *   Key factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\entity_hierarchy\Storage\EntityTreeNodeMapperInterface $entityTreeNodeMapper
   *   Tree node mapper.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserSectionStorageInterface $userSectionStorage, EntityFieldManagerInterface $entityFieldManager, NestedSetStorageFactory $nestedSetStorageFactory, NestedSetNodeKeyFactory $nodeKeyFactory, EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, EntityTreeNodeMapperInterface $entityTreeNodeMapper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $userSectionStorage, $configFactory, $entityTypeManager);
    $this->entityFieldManager = $entityFieldManager;
    $this->nestedSetStorageFactory = $nestedSetStorageFactory;
    $this->keyFactory = $nodeKeyFactory;
    $this->entityTreeNodeMapper = $entityTreeNodeMapper;
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
      $container->get('entity_field.manager'),
      $container->get('entity_hierarchy.nested_set_storage_factory'),
      $container->get('entity_hierarchy.nested_set_node_factory'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('entity_hierarchy.entity_tree_node_mapper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFields($entity_type, $bundle, $parents) {
    $field_name = $this->pluginDefinition['field_name'];
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    // Field has a parent.
    if (isset($field_definitions[$field_name])) {
      return [$field_name => $field_definitions[$field_name]->getLabel()];
    }
    // Field has no parent, so can only be itself.
    $id_field = $this->entityTypeManager->getDefinition($entity_type)->getKey('id');
    if (isset($field_definitions[$id_field])) {
      return [$id_field => $field_definitions[$id_field]->getLabel()];
    }
    return [$id_field => 'ID'];
  }

  /**
   * {@inheritdoc}
   */
  public function alterOptions($field, WorkbenchAccessManagerInterface $manager, array $user_sections = []) {
    return $field;
  }

  /**
   * {@inheritdoc}
   */
  public function getTree() {
    if (!isset($this->tree)) {
      $entity_type_id = $this->pluginDefinition['entity'];
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $field_name = $this->pluginDefinition['field_name'];
      $tree_storage = $this->nestedSetStorageFactory->get($field_name, $entity_type_id);
      $query = $this->entityTypeManager->getStorage($entity_type_id)->getQuery();
      $tree = [];
      $or = $query->orConditionGroup();
      $boolean_fields = $this->config->get('parents');
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
      $aggregate = $this->entityTypeManager->getStorage($entity_type_id)->getAggregateQuery();
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
      $entities = $this->entityTypeManager->getStorage($entity_type_id)->loadMultiple($valid_ids);
      /** @var \PNX\NestedSet\Node $node */
      foreach ($tree_storage->getTree() as $weight => $node) {
        $id = $node->getId();
        if (!in_array($id, $valid_ids)) {
          // Not a valid parent.
          continue;
        }
        foreach ($boolean_parents[$id] as $parent) {
          $tree[$parent][$id] = [
            'label' => isset($entities[$id]) ? $this->generateEntityLabelWithAncestry($entities[$id], $tree_storage, $entity_type_id) : 'N/A',
            'depth' => $node->getDepth(),
            'parents' => [],
            'weight' => $weight,
            'description' => '',
          ];
        }
      }
      $this->tree = $tree;
    }
    return $this->tree;
  }

  /**
   * {@inheritdoc}
   */
  public function options() {
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
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityValues(EntityInterface $entity, $field) {
    // We start with our own ID.
    $values = [$entity->id()];
    $nodeKey = $this->keyFactory->fromEntity($entity);
    foreach ($entity->get($field) as $item) {
      if ($item->isEmpty()) {
        continue;
      }
      $property_name = $item->mainPropertyName();
      $values[] = $item->{$property_name};
      if ($item instanceof EntityReferenceItem) {
        $nodeKey = $this->keyFactory->fromEntity($item->entity);
      }
    }
    $storage = $this->nestedSetStorageFactory->get($this->pluginDefinition['field_name'], $this->pluginDefinition['entity']);
    $ancestors = $storage->findAncestors($nodeKey);
    foreach ($ancestors as $ancestor) {
      $values[] = $ancestor->getId();
    }

    return $values;
  }

}
