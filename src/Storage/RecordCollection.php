<?php

namespace Drupal\entity_hierarchy\Storage;

/**
 * A collection of record objects with useful functions.
 */
class RecordCollection implements \IteratorAggregate, \Countable {

  public function __construct(
    protected array $records
  ) {}

  public function getRecords() {
    return $this->records;
  }

  public function getIterator(): \Traversable {
    return new \RecursiveIteratorIterator(
      new RecordRecursiveIterator($this->records),
      \RecursiveIteratorIterator::SELF_FIRST
    );
  }

  public function count(): int {
    $counts = $this->map(fn($record) => !empty($record->getChildren()) ? count($record->getChildren()) : 0);
    $counts[] = count($this->records);
    return array_sum(array_filter($counts));
  }

  public function map(callable $callable): array {
    $result = [];
    foreach ($this as $record) {
      $result[] = $callable($record);
    }
    return $result;
  }

  public function filter(callable $callable): RecordCollection {
    $this->records = array_filter($this->records, $callable);
    foreach ($this->records as $record) {
      $this->doFilter($callable, $record);
    }
    return $this;
  }

  protected function doFilter(callable $callable, Record &$record) {
    $children = $record->getChildren();
    if (!empty($children)) {
      $filtered = array_filter($children, $callable);
      $record->setChildren($filtered);
      foreach ($filtered as $childRecord) {
        $this->doFilter($callable, $childRecord);
      }
    }
  }

  public function buildTree(): RecordCollection {
    $indexed_records = [];
    foreach ($this->records as $record) {
      $indexed_records[$record->getId()] = $record;
    }

    $new_records = [];
    foreach ($this->records as $record) {
      $target_id = $record->getTargetId();
      if (!empty($indexed_records[$target_id])) {
        $children = $indexed_records[$target_id]->getChildren() ?: [];
        $children[] = $record;
        $indexed_records[$target_id]->setChildren($children);
      }
      else {
        $new_records[] = $record;
      }
    }

    $this->records = $new_records;
    return $this;
  }

  public function flatten(): RecordCollection {
    $this->records = $this->map(fn($record) => $record);
    foreach ($this->records as $record) {
      $record->setChildren([]);
    }
    return $this;
  }

}
