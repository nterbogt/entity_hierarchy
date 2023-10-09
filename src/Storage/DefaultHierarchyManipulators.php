<?php

namespace Drupal\entity_hierarchy\Storage;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Defines default tree manipulators for entity hierarchy.
 */
class DefaultHierarchyManipulators {

  /**
   * Constructs a new DefaultHierarchyManipulators object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager
  ) {}

  /**
   * @param \Drupal\entity_hierarchy\Storage\Record[] $records
   *   The records to check.
   */
  public function collectEntityIds(array $records, array &$ids) {
    foreach ($records as $record) {
      $ids[] = $record->getId();
    }
    return $records;
  }

  /**
   * @param \Drupal\entity_hierarchy\Storage\Record[] $records
   *   The records to check.
   */
  public function collectEntities(array $records, array &$entities) {
    $ids = [];
    $this->collectEntityIds($records ,$ids);
    $first = reset($records);
    $entities = $this->entityTypeManager->getStorage($first->getType())->loadMultiple($ids);
    return $records;
  }

}
