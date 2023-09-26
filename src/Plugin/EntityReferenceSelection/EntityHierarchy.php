<?php

namespace Drupal\entity_hierarchy\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\entity_hierarchy\Information\AncestryLabelTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for entity reference selection that includes lineage.
 *
 * @EntityReferenceSelection(
 *   id = "entity_hierarchy",
 *   label = @Translation("Entity Hierarchy"),
 *   group = "entity_hierarchy",
 *   weight = 0,
 *   deriver = "Drupal\entity_hierarchy\Plugin\Derivative\EntityHierarchySelectionDeriver"
 * )
 */
class EntityHierarchy extends DefaultSelection {

  use AncestryLabelTrait;

  protected $queryBuilderFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var self $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->queryBuilderFactory = $container->get('entity_hierarchy.hierarchy_query_builder_factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $target_type = $this->configuration['target_type'];

    $query = $this->buildEntityQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $result = $query->execute();

    if (empty($result)) {
      return [];
    }

    $options = [];
    $entities = $this->entityTypeManager->getStorage($target_type)->loadMultiple($result);

    // We assume target and definition are one and the same, as there is no
    // point in a hierarchy if you're referencing something else, you can't
    // go more than one level deep.
    $queryBuilder = $this->queryBuilderFactory->get($this->pluginDefinition['field_name'], $target_type);
    foreach ($entities as $entity_id => $entity) {
      $bundle = $entity->bundle();
      $label = $this->generateEntityLabelWithAncestry($entity, $queryBuilder);
      $options[$bundle][$entity_id] = Html::escape($label);
    }

    return $options;
  }

}
