<?php

namespace Drupal\entity_hierarchy\Storage;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines a class for handling a parent entity is updated to a new revision.
 */
class ParentEntityRevisionUpdater extends ParentEntityReactionBase {

  /**
   * Move children from old revision to new revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $oldRevision
   *   Old revision.
   * @param \Drupal\Core\Entity\ContentEntityInterface $newRevision
   *   New revision.
   */
  public function moveChildren(ContentEntityInterface $oldRevision, ContentEntityInterface $newRevision) {
    if (!$newRevision->isDefaultRevision()) {
      // We don't move children to a non-default revision.
      return;
    }
    if ($newRevision->getRevisionId() == $oldRevision->getRevisionId()) {
      // We don't move anything if the revision isn't changing.
      return;
    }
    if (!$fields = $this->parentCandidate->getCandidateFields($newRevision)) {
      // There are no fields that could point to this entity.
      return;
    }
    $oldNodeKey = $this->nodeKeyFactory->fromEntity($oldRevision);
    $newNodeKey = $this->nodeKeyFactory->fromEntity($newRevision);
    foreach ($fields as $field_name) {
      /** @var \Pnx\NestedSet\NestedSetInterface $storage */
      $storage = $this->nestedSetStorageFactory->get($field_name, $newRevision->getEntityTypeId());
      if (!$existingParent = $storage->getNode($newNodeKey)) {
        $existingParent = $storage->addRootNode($newNodeKey);
      }
      // We process them in reverse because moveSubTreeBelow inserts the item as
      // the first child of the parent. This means the first child is moved last
      // and is returned to the first position.
      foreach (array_reverse($storage->findChildren($oldNodeKey)) as $child) {
        // We have to fetch the parent and child node fresh each time to allow
        // for updates that happened in each call to moveSubTreeBelow.
        $storage->moveSubTreeBelow($storage->getNode($existingParent->getNodeKey()), $storage->getNode($child->getNodeKey()));
      }
    }
  }

}
