<?php

declare(strict_types=1);

namespace Drupal\entity_hierarchy\Storage;

/**
 * A collection of functions that can be used to compose filters.
 */
class RecordCollectionCallable {

  /**
   * Filter access based on entity 'view label' access.
   *
   * @param \Drupal\entity_hierarchy\Storage\Record $record
   *   The record to check entity access on.
   *
   * @return bool|\Drupal\Core\Access\AccessResult
   *   A boolean as required by array_filter.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function viewLabelAccessFilter(Record $record) {
    if ($entity = $record->getEntity()) {
      return $entity->access('view label');
    }
    return FALSE;
  }

  /**
   * Sort by record weight.
   *
   * @param \Drupal\entity_hierarchy\Storage\Record $a
   *   The left record to compare.
   * @param \Drupal\entity_hierarchy\Storage\Record $b
   *   The right record to compare.
   *
   * @return int
   *   A sorting response of -1, 0, 1.
   */
  public static function weightSort(Record $a, Record $b) {
    return $a->getWeight() <=> $b->getWeight();
  }

  /**
   * A map function for getting the entity IDs of all the records.
   *
   * @param \Drupal\entity_hierarchy\Storage\Record $record
   *
   * @return int
   *   The entity ID from the record.
   */
  public static function entityIdMap(Record $record) {
    return $record->getId();
  }

}
