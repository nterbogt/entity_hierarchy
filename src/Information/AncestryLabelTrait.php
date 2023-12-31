<?php

declare(strict_types=1);

namespace Drupal\entity_hierarchy\Information;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_hierarchy\Storage\QueryBuilder;
use Drupal\entity_hierarchy\Storage\Record;
use Drupal\entity_hierarchy\Storage\RecordCollectionCallable;

/**
 * Provides a trait for ancestry labels.
 */
trait AncestryLabelTrait {

  /**
   * Generate labels including ancestry.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity to generate label for.
   * @param \Drupal\entity_hierarchy\Storage\QueryBuilder $queryBuilder
   *   Query builder instance.
   * @param array $tags
   *   Cache tags.
   *
   * @return string
   *   Label with ancestry if applicable.
   */
  protected function generateEntityLabelWithAncestry(ContentEntityInterface $entity, QueryBuilder $queryBuilder, &$tags = []) {
    $ancestors = $queryBuilder->findAncestors($entity)
      ->filter(function (Record $record) use ($entity) {
        if (!RecordCollectionCallable::viewLabelAccessFilter($record)) {
          return FALSE;
        }
        // Remove ourself.
        return $record->getId() != $entity->id();
      });
    $ancestors_labels = [];
    foreach ($ancestors as $record) {
      $recordEntity = $record->getEntity();
      $ancestors_labels[] = $recordEntity->label();
      foreach ($recordEntity->getCacheTags() as $tag) {
        $tags[] = $tag;
      }
    }
    if (!$ancestors || !$ancestors_labels) {
      // No parents.
      return $entity->label();
    }
    return sprintf('%s (%s)', $entity->label(), implode(' ❭ ', $ancestors_labels));
  }

}
