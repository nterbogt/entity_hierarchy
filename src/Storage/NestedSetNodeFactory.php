<?php

namespace Drupal\entity_hierarchy\Storage;

use Drupal\Core\Entity\ContentEntityInterface;
use PNX\NestedSet\Node;

/**
 * Defines a class for turning Drupal entities into nested set nodes.
 */
class NestedSetNodeFactory {

  /**
   * Creates a new node from an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity to convert into nested set node.
   *
   * @return \PNX\NestedSet\Node
   *   New node.
   */
  public function fromEntity(ContentEntityInterface $entity) {
    $id = $entity->id();
    if (!$revision_id = $entity->getRevisionId()) {
      $revision_id = $id;
    }
    return new Node($id, $revision_id);
  }

}
