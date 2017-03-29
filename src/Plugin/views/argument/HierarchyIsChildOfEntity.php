<?php

namespace Drupal\entity_hierarchy\Plugin\views\argument;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory;
use Drupal\entity_hierarchy\Storage\NestedSetStorageFactory;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Argument to limit to children of an entity.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("entity_hierarchy_argument_is_child_of_entity")
 */
class HierarchyIsChildOfEntity extends ArgumentPluginBase {

  /**
   * Set storage.
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
   * Node key factory.
   *
   * @var \Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory
   */
  protected $nodeKeyFactory;

  /**
   * Table prefix for nested set.
   *
   * DBAL doesn't support table prefixes, so we must ensure to respect it.
   *
   * @var string
   */
  protected $nestedSetPrefix = '';

  /**
   * Constructs a new HierarchyIsChildOfEntity object.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Definition.
   * @param \Drupal\entity_hierarchy\Storage\NestedSetStorageFactory $nestedSetStorageFactory
   *   Nested set storage.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory $nodeKeyFactory
   *   Node key factory.
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, NestedSetStorageFactory $nestedSetStorageFactory, EntityTypeManagerInterface $entityTypeManager, NestedSetNodeKeyFactory $nodeKeyFactory, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->nestedSetStorageFactory = $nestedSetStorageFactory;
    $this->entityTypeManager = $entityTypeManager;
    $this->nodeKeyFactory = $nodeKeyFactory;
    $this->nestedSetPrefix = $database->tablePrefix();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_hierarchy.nested_set_storage_factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_hierarchy.nested_set_node_factory'),
      $container->get('database')
    );
  }

  /**
   * Set up the query for this argument.
   *
   * The argument sent may be found at $this->argument.
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    // Load the actual entity.
    $filtered = FALSE;
    if ($entity = $this->loadParentEntity()) {
      $stub = $this->nodeKeyFactory->fromEntity($entity);
      if ($node = $this->getTreeStorage()->getNode($stub)) {
        // Query between a range.
        $filtered = TRUE;
        $expression = "$this->tableAlias.$this->realField BETWEEN :lower and :upper AND $this->tableAlias.$this->realField <> :lower";
        $arguments = [
          ':lower' => $node->getLeft(),
          ':upper' => $node->getRight(),
        ];
        if ($depth = $this->options['depth']) {
          $expression .= " AND $this->tableAlias.depth <= :depth";
          $arguments[':depth'] = $node->getDepth() + $depth;
        }
        $this->query->addWhereExpression(0, $expression, $arguments);
      }
    }
    // The parent entity doesn't exist, or isn't in the tree and hence has no
    // children.
    if (!$filtered) {
      // Add a killswitch.
      $this->query->addWhereExpression(0, '1 <> 1');
    }
  }

  /**
   * Loads the parent entity from the argument.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Parent entity if exists.
   */
  protected function loadParentEntity() {
    return $this->entityTypeManager->getStorage($this->getEntityType())->load($this->argument);
  }

  /**
   * Returns the tree storage.
   *
   * @return \Drupal\entity_hierarchy\Storage\NestedSetStorage
   *   Tree storage.
   */
  protected function getTreeStorage() {
    return $this->nestedSetStorageFactory->fromTableName($this->nestedSetPrefix . $this->table);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $options = range(1, 50);
    $form['depth'] = [
      '#type' => 'select',
      '#empty_option' => $this->t('No restriction'),
      '#empty_value' => 0,
      '#options' => array_combine($options, $options),
      '#title' => $this->t('Depth'),
      '#default_value' => $this->options['depth'],
      '#description' => $this->t('Filter to children that are at most this many levels deeper than their parent. E.g. for immediate children, select 1.'),
    ];
    parent::buildOptionsForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Allow filtering depth as well.
    $options['depth'] = ['default' => 0];

    return $options;
  }

}
