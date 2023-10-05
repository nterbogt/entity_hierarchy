<?php

namespace Drupal\entity_hierarchy\Information;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_hierarchy\Storage\EntityHierarchyQueryBuilder;

/**
 * Provides a trait for ancestry labels.
 */
trait AncestryLabelTrait {

  /**
   * Generate labels including ancestry.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity to generate label for.
   * @param \PNX\NestedSet\NestedSetInterface|\Drupal\entity_hierarchy\Storage\NestedSetStorage $storage
   *   Tree storage.
   * @param array $tags
   *   Cache tags.
   *
   * @return string
   *   Label with ancestry if applicable.
   */
  protected function generateEntityLabelWithAncestry(ContentEntityInterface $entity, EntityHierarchyQueryBuilder $queryBuilder, &$tags = []) {
    $ancestors = $queryBuilder->findAncestors($entity);
    // Remove ourself.
    array_pop($ancestors);
    $ancestor_entities = $queryBuilder->getEntities($ancestors);
    $ancestors_labels = [];
    foreach ($ancestor_entities as $ancestor_node) {
      $ancestors_labels[] = $ancestor_node->label();
      foreach ($ancestor_node->getCacheTags() as $tag) {
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
