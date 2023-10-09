<?php

namespace Drupal\entity_hierarchy\Information;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_hierarchy\Storage\EntityHierarchyQueryBuilderFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for building a list of child entity warnings.
 */
class ChildEntityWarningBuilder implements ContainerInjectionInterface {

  /**
   * Constructs a new ChildEntityWarningBuilder object.
   *
   * @param \Drupal\entity_hierarchy\Information\ParentCandidateInterface $parentCandidate
   *   Parent candidate service.
   * @param \Drupal\entity_hierarchy\Storage\EntityHierarchyQueryBuilderFactory $queryBuilderFactory
   *   Query builder factory.
   */
  public function __construct(
    protected ParentCandidateInterface $parentCandidate,
    protected EntityHierarchyQueryBuilderFactory $queryBuilderFactory
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_hierarchy.information.parent_candidate'),
      $container->get('entity_hierarchy.hierarchy_query_builder_factory')
    );
  }

  /**
   * Gets warning about child entities before deleting a parent.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity to be deleted.
   *
   * @return \Drupal\entity_hierarchy\Information\ChildEntityWarning[]
   *   Array of warning value objects.
   */
  public function buildChildEntityWarnings(ContentEntityInterface $entity) {
    $return = [];
    if ($fields = $this->parentCandidate->getCandidateFields($entity)) {
      $cache = new CacheableMetadata();
      foreach ($fields as $field_name) {
        $queryBuilder = $this->queryBuilderFactory->get($field_name, $entity->getEntityTypeId());
        $records = $queryBuilder->findChildren($entity);
        $entities = [];
        $manipulators = [
          ['callable' => 'entity_hierarchy.default_manipulators:collectEntities', 'args' => [&$entities]],
        ];
        $queryBuilder->transform($records, $manipulators);
        $parent = $queryBuilder->findParent($entity);
        $return[] = new ChildEntityWarning($entities, $cache, $parent);
      }
    }
    return $return;
  }

}
