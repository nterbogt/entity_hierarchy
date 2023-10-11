<?php

namespace Drupal\entity_hierarchy\Storage;

use Traversable;

/**
 * Defines a factory for creating a nested set storage handler for hierarchies.
 */
class RecordCollection implements \IteratorAggregate, \Countable {

  public function __construct(
    protected array $records
  ) {}

  public function getRecords() {
    return $this->records;
  }

  public function getIterator(): Traversable {
    yield from $this->records;
  }

  public function count(): int {
    return count($this->records);
  }

  public function map(callable $callable): array {
    return array_map($callable, $this->records);
  }

  public function filter(callable $callable): RecordCollection {
    $this->records = array_filter($this->records, $callable);
    return $this;
  }

}
