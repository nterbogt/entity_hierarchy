<?php

namespace Drupal\entity_hierarchy\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_hierarchy\Storage\QueryBuilderFactory;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A handler to provide a field that show hierarchy depth of item.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("entity_hierarchy_depth")
 */
class HierarchyDepth extends FieldPluginBase {

  /**
   * Constructs a new HierarchyDepth object.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected readonly QueryBuilderFactory $queryBuilderFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_hierarchy.query_builder_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if ($entity = $this->getEntity($values)) {
      $queryBuilder = $this->queryBuilderFactory->get($this->configuration['entity_hierarchy_field'], $entity->getEntityTypeId());
      return $queryBuilder->findDepth($entity);
    }

    return '';
  }

}
