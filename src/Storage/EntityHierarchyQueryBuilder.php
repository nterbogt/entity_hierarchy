<?php

namespace Drupal\entity_hierarchy\Storage;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Psr\Log\LoggerInterface;
use PDO;

class EntityHierarchyQueryBuilder {

  protected $tables = [];

  protected $columns = [];

  public function __construct(
    protected FieldStorageDefinitionInterface $fieldStorageDefinition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
    protected LoggerInterface $logger
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

  private function getTablePrefix() {
    return $this->database->tablePrefix();
  }

  public function findParent(ContentEntityInterface $entity): ?ContentEntityInterface {
    return $entity->get($this->fieldStorageDefinition->getName())->entity;
  }

  /**
   * @return \Drupal\entity_hierarchy\Storage\Record[]
   */
  public function populateEntities($records): array {
    foreach ($records as $record) {
      // @todo Optimise loading.
      $record->setEntity($this->entityTypeManager->getStorage($this->fieldStorageDefinition->getTargetEntityTypeId())->load($record->getId()));
    }
    return $records;
  }

  public function getEntities($records) {
    $new_records = [];
    foreach ($records as $record) {
      // @todo Optimise loading.
      if ($entity = $this->entityTypeManager->getStorage($this->fieldStorageDefinition->getTargetEntityTypeId())->load($record->getId())) {
        $new_records[] = $entity;
      }
    }
    return $new_records;
  }

  /**
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *
   * @return \Drupal\entity_hierarchy\Storage\Record[]
   */
  public function findChildren(ContentEntityInterface $entity): array {
    $query = $this->database->select($this->tables['entity'], 'e');
    $query->addField('e', $this->columns['id'], 'id');
    $query->addField('e', $this->columns['revision_id'], 'revision_id');
    $query->addField('e', $this->columns['target_id'], 'target_id');
    $query->addField('e', $this->columns['weight'], 'weight');
    $result = $query->condition($this->columns['target_id'], $entity->id())
      ->orderBy($this->columns['weight'])
      ->execute();
    $records = $result->fetchAll(PDO::FETCH_CLASS, '\Drupal\entity_hierarchy\Storage\Record');
    $type = $this->fieldStorageDefinition->getTargetEntityTypeId();
    array_walk($records, function ($record) use ($type) {
      $record->setType($type);
      $record->setDepth(1);
    });
    return $records;
  }

  protected function getAncestorSql(): string {
    // @todo Fix entity_id reference.
    $table_name = $this->getTablePrefix() . $this->tables['entity'];
    $revision_table_name = $this->getTablePrefix() . $this->tables['entity_revision'];
    $column_id = $this->columns['id'];
    $column_revision_id = $this->columns['revision_id'];
    $column_target_id = $this->columns['target_id'];
    $column_weight = $this->columns['weight'];
    $sql = <<<CTESQL
WITH RECURSIVE ancestors AS
(
  SELECT $column_id AS id, $column_target_id AS target_id, $column_weight AS weight, $column_revision_id as revision_id, 0 AS depth FROM $revision_table_name WHERE $column_id = :id AND $column_revision_id = :revision_id
  UNION ALL
  SELECT c.$column_id AS id, c.$column_target_id AS target_id, c.$column_weight AS weight, c.$column_revision_id as revision_id, ancestors.depth-1 FROM $table_name c
  JOIN ancestors ON c.$column_id=ancestors.target_id
) 
CTESQL;
    return $sql;
  }

  public function findRoot(ContentEntityInterface $entity): ?ContentEntityInterface {
    $sql = $this->getAncestorSql() . "SELECT target_id FROM ancestors ORDER BY depth LIMIT 1";
    $result = $this->database->query($sql, [
      ':id' => $entity->id(),
      ':revision_id' => $entity->getRevisionId() ?: $entity->id(),
    ]);
    if ($record = $result->fetchObject()) {
      return $this->entityTypeManager->getStorage($this->fieldStorageDefinition->getTargetEntityTypeId())->load($record->target_id);
    }
    return NULL;
  }

  public function findDepth(ContentEntityInterface $entity) {
    $sql = $this->getAncestorSql() . "SELECT depth FROM ancestors ORDER BY depth LIMIT 1";
    $result = $this->database->query($sql, [
      ':id' => $entity->id(),
      ':revision_id' => $entity->getRevisionId() ?: $entity->id(),
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
   * @return \Drupal\entity_hierarchy\Storage\Record[]
   */
  public function findAncestors(ContentEntityInterface $entity) {
    $sql = $this->getAncestorSql() . "SELECT * FROM ancestors ORDER BY depth";
    $result = $this->database->query($sql, [
      ':id' => $entity->id(),
      ':revision_id' => $entity->getRevisionId() ?: $entity->id(),
    ]);
    $records = $result->fetchAll(PDO::FETCH_CLASS, '\Drupal\entity_hierarchy\Storage\Record');
    $type = $this->fieldStorageDefinition->getTargetEntityTypeId();
    array_walk($records, function ($record) use ($type) {
      $record->setType($type);
    });
    if (!$this->fieldStorageDefinition->isBaseField()) {
      // Drupal doesn't store an empty row for a NULL parent. Add it back in.
      $first_record = reset($records);
      $record = Record::create($type, $first_record->getTargetId(), 0, $first_record->getDepth() - 1);
      array_unshift($records, $record);
    }
    return $records;
  }

  protected function getDescendantSql(): string {
    // @todo Fix entity_id reference.
    $table_name = $this->getTablePrefix() . $this->tables['entity'];
    $column_id = $this->columns['id'];
    $column_revision_id = $this->columns['revision_id'];
    $column_target_id = $this->columns['target_id'];
    $column_weight = $this->columns['weight'];
    $sql = <<<CTESQL
WITH RECURSIVE descendants AS
(
  SELECT $column_id as id, $column_target_id as target_id, $column_revision_id as revision_id, $column_weight as weight, 1 AS depth
  FROM $table_name
  WHERE $column_target_id = :target_id
  UNION ALL
  SELECT c.$column_id as id, c.$column_target_id as target_id, c.$column_revision_id as revision_id, c.$column_weight as weight, descendants.depth+1
  FROM $table_name c
  JOIN descendants ON descendants.id=c.$column_target_id
) 
CTESQL;
    return $sql;
  }

  public function findDescendants(ContentEntityInterface $entity, int $depth = 0, int $start = 1) {
    $sql = $this->getDescendantSql() . "SELECT * FROM descendants WHERE depth >= :start";
    $params = [
      ':target_id' => $entity->id(),
      ':start' => $start,
    ];
    if ($depth > 0) {
      $sql .= " AND depth < :depth";
      $params[':depth'] = $start + $depth;
    }
    $result = $this->database->query($sql, $params);
    $records = [];
    foreach ($result as $record) {
      $records[] = $record;
    }
    return $records;
  }

}