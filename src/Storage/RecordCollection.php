<?php

namespace Drupal\entity_hierarchy\Storage;

/**
 * A collection of record objects with useful functions.
 */
class RecordCollection implements \IteratorAggregate, \Countable {

  /**
   * Constructor for a record collection.
   *
   * @param \Drupal\entity_hierarchy\Storage\Record[] $records
   *   The records to put in a collection.
   */
  public function __construct(
    protected array $records
  ) {}

  /**
   * The raw underlying records in the collection.
   *
   * @return \Drupal\entity_hierarchy\Storage\Record[]
   *   The records.
   */
  public function getRecords() {
    return $this->records;
  }

  /**
   * An iterator that knows how to handle the collection tree structure.
   *
   * @return \Traversable<\Drupal\entity_hierarchy\Storage\Record>
   *   The iterator so that a collection can be passed directly into foreach.
   */
  public function getIterator(): \Traversable {
    return new \RecursiveIteratorIterator(
      new RecordRecursiveIterator($this->records),
      \RecursiveIteratorIterator::SELF_FIRST
    );
  }

  /**
   * Count the number of records in the collection.
   *
   * @return int
   *   The count of the records, traversing hierarchy.
   */
  public function count(): int {
    $counts = $this->map(fn($record) => !empty($record->getChildren()) ? count($record->getChildren()) : 0);
    $counts[] = count($this->records);
    return array_sum(array_filter($counts));
  }

  /**
   * Synonymous with array_map but can traverse the hierarchy tree.
   *
   * @param callable $callable
   *   Function to call on records.
   *
   * @return array
   *   The resulting output. Will be flattened.
   */
  public function map(callable $callable): array {
    $result = [];
    foreach ($this as $record) {
      $result[] = $callable($record);
    }
    return $result;
  }

  /**
   * Synonymous with array_filter but can traverse the hierarchy tree.
   *
   * When filtering a tree structure, the entire limb will be removed if one
   * of the parents matches the filter.
   *
   * @param callable $callable
   *   Callable to filter out records.
   *
   * @return $this
   *   Make this chainable.
   */
  public function filter(callable $callable): RecordCollection {
    $this->records = array_filter($this->records, $callable);
    foreach ($this->records as $record) {
      $this->doFilter($callable, $record);
    }
    return $this;
  }

  /**
   * Underlying worker function to filter the tree.
   *
   * @param callable $callable
   *   Callable to filter out records.
   * @param \Drupal\entity_hierarchy\Storage\Record $record
   *   The current record being filtered.
   */
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

  /**
   * Synonymous with usort but can traverse the hierarchy tree.
   *
   * @param callable|null $callable
   *   Callable to sort records. If null, sort by weight.
   *
   * @return $this
   *   Make this chainable.
   */
  public function sort(callable $callable = NULL): RecordCollection {
    if (!isset($callable)) {
      $callable = fn (Record $a, Record $b) => $a->getWeight() <=> $b->getWeight();
    }
    usort($this->records, $callable);
    foreach ($this->records as $record) {
      $this->doSort($callable, $record);
    }
    return $this;
  }

  /**
   * Underlying worker function to sort records..
   *
   * @param callable $callable
   *   Callable to sort records.
   * @param \Drupal\entity_hierarchy\Storage\Record $record
   *   The current record being sorted.
   */
  protected function doSort(callable $callable, Record &$record) {
    $children = $record->getChildren();
    if (!empty($children)) {
      usort($children, $callable);
      foreach ($children as $childRecord) {
        $this->doSort($callable, $childRecord);
      }
    }
  }

  /**
   * Iterate the records and turn them into a tree.
   *
   * @return $this
   *   Make this chainable.
   */
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

  /**
   * Flatten out records that are in a tree.
   *
   * @return $this
   *   Make this chainable.
   */
  public function flatten(): RecordCollection {
    $this->records = $this->map(fn($record) => $record);
    foreach ($this->records as $record) {
      $record->setChildren([]);
    }
    return $this;
  }

}
