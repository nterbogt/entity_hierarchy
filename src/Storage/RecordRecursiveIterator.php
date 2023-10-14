<?php

namespace Drupal\entity_hierarchy\Storage;

/**
 * Defines a factory for creating a nested set storage handler for hierarchies.
 */
class RecordRecursiveIterator extends \RecursiveArrayIterator {

  /**
   * {@inheritDoc}
   */
  public function hasChildren(): bool {
    return !empty($this->current()->getChildren());
  }

  /**
   * {@inheritDoc}
   */
  public function getChildren(): ?RecordRecursiveIterator {
    return new RecordRecursiveIterator($this->current()->getChildren());
  }

}
