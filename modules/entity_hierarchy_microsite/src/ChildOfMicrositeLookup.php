<?php

namespace Drupal\entity_hierarchy_microsite;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_hierarchy\Storage\QueryBuilderFactory;
use Drupal\entity_hierarchy\Storage\RecordCollectionCallable;
use Drupal\node\NodeInterface;

/**
 * Defines a class for looking up a microsite given a node.
 */
class ChildOfMicrositeLookup implements ChildOfMicrositeLookupInterface {

  /**
   * Constructs a new ChildOfMicrositeLookup.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\entity_hierarchy\Storage\QueryBuilderFactory $queryBuilderFactory
   *   Query builder factory.
   */
  public function __construct(
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected QueryBuilderFactory $queryBuilderFactory
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function findMicrositesForNodeAndField(NodeInterface $node, $field_name) {
    $ids = [];
    if ($node->hasField($field_name) &&
    !$node->get($field_name)->isEmpty()) {
      $queryBuilder = $this->queryBuilderFactory->get($field_name, 'node');
      $ids = $queryBuilder->findAncestors($node)
        ->map(RecordCollectionCallable::entityIdMap(...));
    }
    $ids[] = $node->id();
    $entityStorage = $this->entityTypeManager->getStorage('entity_hierarchy_microsite');
    return $entityStorage->loadMultiple($entityStorage
      ->getQuery()
      ->accessCheck(FALSE)
      ->sort('id')
      ->condition('home', $ids, 'IN')
      ->execute());
  }

}
