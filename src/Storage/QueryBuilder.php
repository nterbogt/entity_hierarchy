<?php

namespace Drupal\entity_hierarchy\Storage;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Psr\Log\LoggerInterface;

/**
 * The query builder for a specific hierarchical field instance.
 */
class QueryBuilder {

  protected $tables = [];

  protected $columns = [];

  /**
   * Constructor for query builder.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $fieldStorageDefinition
   *   The field storage for this hierarchy field.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manage service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    protected FieldStorageDefinitionInterface $fieldStorageDefinition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
    protected LoggerInterface $logger,
  ) {
    $tableMapping = $this->entityTypeManager->getStorage($this->fieldStorageDefinition->getTargetEntityTypeId())->getTableMapping();

    // Get table definitions.
    $table_names = $tableMapping->getAllFieldTableNames($this->fieldStorageDefinition->getName());
    $this->tables = [
      'entity' => $table_names[0],
    ];
    $this->tables['entity_revision'] = !empty($table_names[1]) ? $table_names[1] : $table_names[0];

    // Get column definitions.
    $base_field_id = $this->fieldStorageDefinition->isBaseField() ? 'id' : 'entity_id';
    $this->columns = [
      'id' => $base_field_id,
      'revision_id' => $this->fieldStorageDefinition->isRevisionable() ? 'revision_id' : $base_field_id,
      'target_id' => $tableMapping->getFieldColumnName($this->fieldStorageDefinition, 'target_id'),
      'weight' => $tableMapping->getFieldColumnName($this->fieldStorageDefinition, 'weight'),
    ];
  }

  /**
   * The table prefix.
   *
   * @return string
   *   The table prefix.
   */
  private function getTablePrefix() {
    return $this->database->getPrefix();
  }

  /**
   * Build the CTE query that can traverse ancestors.
   *
   * @return string
   *   SQL to prefix ancestor queries and define the 'ancestors' table.
   */
  protected function getAncestorSql(): string {
    $table_name = $this->getTablePrefix() . $this->tables['entity'];
    $revision_table_name = $this->getTablePrefix() . $this->tables['entity_revision'];
    $column_id = $this->columns['id'];
    $column_revision_id = $this->columns['revision_id'];
    $column_target_id = $this->columns['target_id'];
    $column_weight = $this->columns['weight'];
    $sql = <<<CTESQL
WITH RECURSIVE ancestors AS
(
  SELECT $column_id AS id, $column_revision_id as revisionId, $column_target_id AS targetId, $column_weight AS weight, 0 AS depth FROM $revision_table_name WHERE $column_id = :id AND $column_revision_id = :revisionId
  UNION ALL
  SELECT c.$column_id, c.$column_revision_id, c.$column_target_id, c.$column_weight, ancestors.depth-1 FROM $table_name c
  JOIN ancestors ON c.$column_id=ancestors.targetId
)
CTESQL;
    return $sql;
  }

  /**
   * Find the ancestor records of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to find the depth of.
   *
   * @return \Drupal\entity_hierarchy\Storage\RecordCollection
   *   The collection of records returned for the ancestor query.
   */
  public function findAncestors(ContentEntityInterface $entity): RecordCollection {
    $sql = $this->getAncestorSql() . " SELECT * FROM ancestors ORDER BY depth";
    $result = $this->database->query($sql, [
      ':id' => $entity->id(),
      ':revisionId' => $entity->getRevisionId() ?: $entity->id(),
    ]);
    $records = $result->fetchAll(\PDO::FETCH_CLASS, '\Drupal\entity_hierarchy\Storage\Record');
    $type = $this->fieldStorageDefinition->getTargetEntityTypeId();
    array_walk($records, function (Record $record) use ($type) {
      $record->setType($type);
    });
    if (!$this->fieldStorageDefinition->isBaseField()) {
      // Drupal doesn't store an empty row for a NULL parent. Add it back in.
      if ($first_record = reset($records)) {
        $record = Record::create($type, $first_record->getTargetId(), 0, $first_record->getDepth() - 1);
      }
      else {
        $record = Record::create($type, $entity->id(), 0, 0);
      }
      array_unshift($records, $record);
    }
    return new RecordCollection($records);
  }

  /**
   * Find the root record of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to find the depth of.
   *
   * @return \Drupal\entity_hierarchy\Storage\Record
   *   The root record of an ancestor query.
   */
  public function findRoot(ContentEntityInterface $entity): ?Record {
    $ancestors = $this->findAncestors($entity);
    // Convert FALSE to NULL.
    return $ancestors->getIterator()->current() ?: NULL;
  }

  /**
   * Find the parent of an entity.
   *
   * This isn't really a query builder function but adding here for
   * completeness.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to find the depth of.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The parent entity of an entity.
   */
  public function findParent(ContentEntityInterface $entity): ?ContentEntityInterface {
    return $entity->get($this->fieldStorageDefinition->getName())->entity;
  }

  /**
   * Find the depth of an entity from the root.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to find the depth of.
   *
   * @return int
   *   The depth.
   */
  public function findDepth(ContentEntityInterface $entity): int {
    $sql = $this->getAncestorSql() . " SELECT depth FROM ancestors ORDER BY depth LIMIT 1";
    $result = $this->database->query($sql, [
      ':id' => $entity->id(),
      ':revisionId' => $entity->getRevisionId() ?: $entity->id(),
    ]);
    if ($object = $result->fetchObject()) {
      $depth = $object->depth * -1;
      if (!$this->fieldStorageDefinition->isBaseField()) {
        // Non base fields don't have a record where parent is null. Compensate.
        $depth = $depth + 1;
      }
      return $depth;
    }
    return 0;
  }

  /**
   * Build the CTE query that can traverse descendants.
   *
   * @return string
   *   SQL to prefix descendants queries and define the 'descendants' table.
   */
  protected function getDescendantSql(): string {
    $table_name = $this->getTablePrefix() . $this->tables['entity'];
    $column_id = $this->columns['id'];
    $column_revision_id = $this->columns['revision_id'];
    $column_target_id = $this->columns['target_id'];
    $column_weight = $this->columns['weight'];
    $sql = <<<CTESQL
WITH RECURSIVE descendants AS
(
  SELECT $column_id as id, $column_revision_id as revisionId, $column_target_id as targetId, $column_weight as weight, 1 AS depth
  FROM $table_name
  WHERE $column_target_id = :targetId
  UNION ALL
  SELECT c.$column_id, c.$column_revision_id, c.$column_target_id, c.$column_weight, descendants.depth+1
  FROM $table_name c
  JOIN descendants ON descendants.id=c.$column_target_id
)
CTESQL;
    return $sql;
  }

  /**
   * Find the descendant records of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to find the descendants of.
   * @param int $depth
   *   The depth of descendants to load.
   * @param int $start
   *   The starting depth.
   *
   * @return \Drupal\entity_hierarchy\Storage\RecordCollection
   *   A collection of records reflecting the descendants of the entity.
   */
  public function findDescendants(ContentEntityInterface $entity, int $depth = 0, int $start = 1): RecordCollection {
    $sql = $this->getDescendantSql() . " SELECT id, revisionId, targetId, weight, depth FROM descendants WHERE depth >= :start";
    $params = [
      ':targetId' => $entity->id(),
      ':start' => $start,
    ];
    if ($depth > 0) {
      $sql .= " AND depth < :depth";
      $params[':depth'] = $start + $depth;
    }
    $sql .= " ORDER by weight, id";
    $result = $this->database->query($sql, $params);
    $records = $result->fetchAll(\PDO::FETCH_CLASS, '\Drupal\entity_hierarchy\Storage\Record');
    $type = $this->fieldStorageDefinition->getTargetEntityTypeId();
    array_walk($records, function (Record $record) use ($type) {
      $record->setType($type);
    });
    return new RecordCollection($records);
  }

  /**
   * Helper function to only find depth 1 descendants.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check.
   *
   * @return \Drupal\entity_hierarchy\Storage\RecordCollection
   *   A collection of records reflecting the children of the entity.
   */
  public function findChildren(ContentEntityInterface $entity): RecordCollection {
    return $this->findDescendants($entity, 1);
  }

}
