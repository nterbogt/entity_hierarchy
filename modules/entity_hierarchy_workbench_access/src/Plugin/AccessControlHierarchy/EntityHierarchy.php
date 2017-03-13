<?php

namespace Drupal\entity_hierarchy_workbench_access\Plugin\AccessControlHierarchy;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory;
use Drupal\entity_hierarchy\Storage\NestedSetStorageFactory;
use Drupal\workbench_access\AccessControlHierarchyBase;
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
   * Key factory.
   *
   * @var \Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory
   */
  protected $nodeKeyFactory;

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
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity Field manager.
   * @param \Drupal\entity_hierarchy\Storage\NestedSetStorageFactory $nestedSetStorageFactory
   *   Storage factory.
   * @param \Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory $nodeKeyFactory
   *   Key factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entityFieldManager, NestedSetStorageFactory $nestedSetStorageFactory, NestedSetNodeKeyFactory $nodeKeyFactory, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityFieldManager = $entityFieldManager;
    $this->nestedSetStorageFactory = $nestedSetStorageFactory;
    $this->nodeKeyFactory = $nodeKeyFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('entity_hierarchy.nested_set_storage_factory'),
      $container->get('entity_hierarchy.nested_set_node_factory'),
      $container->get('entity_type.manager')
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
  public function alterOptions($field, WorkbenchAccessManagerInterface $manager) {
    return $field;
  }

  /**
   * {@inheritdoc}
   */
  public function getTree() {
    if (!isset($this->tree)) {
      $config = $this->config('workbench_access.settings');
      $parents = $config->get('parents');
      $entity_type_id = $this->pluginDefinition['entity'];
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $field_name = $this->pluginDefinition['field_name'];
      $tree_storage = $this->nestedSetStorageFactory->get($field_name, $entity_type_id);
      $query = $this->entityTypeManager->getStorage($entity_type_id)->getQuery();
      if ($parents && $bundle = $entity_type->getKey('bundle')) {
        $query->condition($bundle, $parents, 'IN');
      }
      $valid_ids = $query->execute();
      $tree = [];
      $parents = [];
      $current_depth = 0;
      $aggregate = $this->entityTypeManager->getStorage($entity_type_id)->getAggregateQuery();
      $aggregate->groupBy($entity_type->getKey('label'))
        ->groupBy($entity_type->getKey('id'))
        ->condition($entity_type->getKey('id'), $valid_ids, 'IN');
      $labels = $aggregate->execute();
      $labels = array_combine(array_column($labels, $entity_type->getKey('id')), array_column($labels, $entity_type->getKey('label')));
      /** @var \PNX\NestedSet\Node $node */
      foreach ($tree_storage->getTree() as $weight => $node) {
        $id = $node->getId();
        if (!in_array($id, $valid_ids)) {
          // Not a valid parent.
          continue;
        }
        if ($node->getDepth() === 0) {
          $parents = [$id];
          $current_depth = 0;
        }
        elseif ($node->getDepth() > $current_depth) {
          $parents[] = $node->getDepth();
        }
        elseif ($node->getDepth() < $current_depth) {
          array_pop($parents);
          $parents[] = $node->getDepth();
        }
        $tree_id = reset($parents);
        $tree[$tree_id][$id] = [
          'label' => isset($labels[$id]) ? $labels[$id] : 'N/A',
          'depth' => $node->getDepth(),
          'parents' => [],
          'weight' => $weight,
          'description' => '',
        ];
      }
      $this->tree = $tree;
    }
    return $this->tree;
  }

}
