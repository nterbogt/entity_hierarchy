<?php

namespace Drupal\entity_hierarchy\Storage;

/**
 * Defines a factory for creating a nested set storage handler for hierarchies.
 */
class RecordIterator extends \RecursiveArrayIterator {

  /**
   * @inheritdoc
   */
  public function hasChildren(): bool {
    return !empty($this->current()->getChildren());
  }

  /**
   * @inheritdoc
   */
  public function getChildren(): ?RecordIterator {
    return new RecordIterator($this->current()->getChildren());
  }

}
