<?php

namespace Drupal\entity_hierarchy\Storage;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines a factory for creating a nested set storage handler for hierarchies.
 */
class Record {

  protected int $id;

  protected ?int $revision_id = NULL;

  protected ?int $target_id;

  protected int $weight;

  protected int $depth;

  protected string $type;

  protected ?array $children = NULL;

  static public function create(string $type, int $id, int $weight, int $depth, int $target_id = NULL) {
    $record = new Record();
    $record->type = $type;
    $record->id = $id;
    $record->weight = $weight;
    $record->depth = $depth;
    $record->target_id = $target_id;
    return $record;
  }

  public function getId(): int {
    return $this->id;
  }

  public function getRevisionId(): ?int {
    return $this->revision_id;
  }

  public function getTargetId(): ?int {
    return $this->target_id;
  }

  public function getWeight(): int {
    return $this->weight;
  }

  public function getDepth(): int {
    return $this->depth;
  }

  public function getType(): string {
    return $this->type;
  }

  public function setType(string $type): void {
    $this->type = $type;
  }

  public function getEntity(): ?ContentEntityInterface {
    return \Drupal::entityTypeManager()->getStorage($this->type)->load($this->id);
  }

  public function getChildren(): ?array {
    return $this->children;
  }

  public function setChildren(array $children) {
    $this->children = $children;
  }

}
