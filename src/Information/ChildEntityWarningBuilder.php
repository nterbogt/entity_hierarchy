<?php

namespace Drupal\entity_hierarchy\Information;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_hierarchy\Storage\EntityHierarchyQueryBuilderFactory;
use Drupal\entity_hierarchy\Storage\EntityTreeNodeMapperInterface;
use Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory;
use Drupal\entity_hierarchy\Storage\NestedSetStorageFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for building a list of child entity warnings.
 */
class ChildEntityWarningBuilder implements ContainerInjectionInterface {

  protected $parentCandidate;

  /**
   * @var \Drupal\entity_hierarchy\Storage\EntityHierarchyQueryBuilderFactory
   */
  protected $queryBuilderFactory;

  /**
   * Constructs a new ChildEntityWarningBuilder object.
   *
   * @param \Drupal\entity_hierarchy\Information\ParentCandidateInterface $parentCandidate
   *   Parent candidate service.
   */
  public function __construct(ParentCandidateInterface $parentCandidate, EntityHierarchyQueryBuilderFactory $queryBuilderFactory) {
    $this->parentCandidate = $parentCandidate;
    $this->queryBuilderFactory = $queryBuilderFactory;
  }

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
        $entities = $queryBuilder->findChildren($entity);
        $parent = $queryBuilder->findParent($entity);
        $return[] = new ChildEntityWarning($entities, $cache, $parent);
      }
    }
    return $return;
  }

}
