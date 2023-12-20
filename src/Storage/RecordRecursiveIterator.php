<?php

declare(strict_types=1);

namespace Drupal\entity_hierarchy\Storage;

/**
 * Defines a factory for creating a nested set storage handler for hierarchies.
 */
class RecordRecursiveIterator extends \RecursiveArrayIterator {

  /**
   * {@inheritdoc}
   */
  public function hasChildren(): bool {
    return !empty($this->current()->getChildren());
  }

  /**
   * {@inheritdoc}
   */
  public function getChildren(): ?RecordRecursiveIterator {
    return new RecordRecursiveIterator($this->current()->getChildren());
  }

}
