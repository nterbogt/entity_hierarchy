<?php

namespace Drupal\entity_hierarchy\Storage;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines a factory for creating a nested set storage handler for hierarchies.
 */
class Record {

  protected int $id;

  protected ?int $revisionId = NULL;

  protected ?int $targetId;

  protected int $weight;

  protected int $depth;

  protected string $type;

  protected ?array $children = NULL;

  /**
   * Ability to create a manual record for one required edge case.
   *
   * For ancestors, the query builder doesn't add current entity record.
   *
   * @param string $type
   *   The content entity type.
   * @param int $id
   *   The content entity ID.
   * @param int $weight
   *   The weight of the current record relative to peers.
   * @param int $depth
   *   The depth of the current record.
   * @param int|null $targetId
   *   The parent entity ID, if one exists.
   *
   * @return \Drupal\entity_hierarchy\Storage\Record
   *   A record that can be used in RecordCollection.
   */
  public static function create(string $type, int $id, int $weight, int $depth, int $targetId = NULL) {
    $record = new Record();
    $record->type = $type;
    $record->id = $id;
    $record->weight = $weight;
    $record->depth = $depth;
    $record->targetId = $targetId;
    return $record;
  }

  /**
   * The ID of the content entity.
   *
   * @return int
   *   The ID of the content entity.
   */
  public function getId(): int {
    return $this->id;
  }

  /**
   * The Revision ID of the content entity.
   *
   * @return int|null
   *   The Revision ID of the content entity if revisionable. Null otherwise.
   */
  public function getRevisionId(): ?int {
    return $this->revisionId;
  }

  /**
   * The ID of the content entity that is the hierarchical parent.
   *
   * @return int|null
   *   The ID of the parent entity or null if empty.
   */
  public function getTargetId(): ?int {
    return $this->targetId;
  }

  /**
   * The weight of the entity compared to siblings.
   *
   * @return int
   *   The weight of current entity compared to siblings.
   */
  public function getWeight(): int {
    return $this->weight;
  }

  /**
   * The depth of the record relative to the query that was executed.
   *
   * This value will be positive for descendants and negative for ancestors.
   *
   * @return int
   *   The weight of the record based on the query.
   */
  public function getDepth(): int {
    return $this->depth;
  }

  /**
   * The content entity type.
   *
   * This value is stored so that we load the entity without storing a cache.
   *
   * @return string
   *   The current entities type.
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * Set the type for this record.
   *
   * @param string $type
   *   The content entity type.
   */
  public function setType(string $type): void {
    $this->type = $type;
  }

  /**
   * Load the content entity that this record refers to.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The content entity or null if unable to load.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getEntity(): ?ContentEntityInterface {
    return \Drupal::entityTypeManager()->getStorage($this->type)->load($this->id);
  }

  /**
   * When in hierarchical form, the children of this record.
   *
   * This relies on buildTree() being called on the record collection.
   * Can be empty indicating tree but no children.
   *
   * @return \Drupal\entity_hierarchy\Storage\Record[]|null
   *   Null if not in hierarchy, children as array otherwise.
   */
  public function getChildren(): ?array {
    return $this->children;
  }

  /**
   * Set the children to an array of records.
   *
   * @param array $children
   *   The children to set.
   */
  public function setChildren(array $children) {
    $this->children = $children;
  }

}
