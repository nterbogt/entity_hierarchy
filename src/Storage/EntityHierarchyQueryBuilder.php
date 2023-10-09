<?php

namespace Drupal\entity_hierarchy\Storage;

use Drupal\Core\Controller\ControllerResolverInterface;
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
    protected LoggerInterface $logger,
    protected ControllerResolverInterface $controllerResolver
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

  public function transform(array $records, array $manipulators) {
    foreach ($manipulators as $manipulator) {
      $callable = $manipulator['callable'];
      $callable = $this->controllerResolver->getControllerFromDefinition($callable);
      // Prepare the arguments for the menu tree manipulator callable; the first
      // argument is always the menu link tree.
      if (isset($manipulator['args'])) {
        array_unshift($manipulator['args'], $records);
        $records = call_user_func_array($callable, $manipulator['args']);
      }
      else {
        $records = call_user_func($callable, $records);
      }
    }
    return $records;
  }

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
  SELECT $column_id AS id, $column_revision_id as revision_id, $column_target_id AS target_id, $column_weight AS weight, 0 AS depth FROM $revision_table_name WHERE $column_id = :id AND $column_revision_id = :revision_id
  UNION ALL
  SELECT c.$column_id, c.$column_revision_id, c.$column_target_id, c.$column_weight, ancestors.depth-1 FROM $table_name c
  JOIN ancestors ON c.$column_id=ancestors.target_id
)
CTESQL;
    return $sql;
  }

  /**
   * @return \Drupal\entity_hierarchy\Storage\Record[]
   */
  public function findAncestors(ContentEntityInterface $entity) {
    $sql = $this->getAncestorSql() . " SELECT * FROM ancestors ORDER BY depth";
    $result = $this->database->query($sql, [
      ':id' => $entity->id(),
      ':revision_id' => $entity->getRevisionId() ?: $entity->id(),
    ]);
    $records = $result->fetchAll(PDO::FETCH_CLASS, '\Drupal\entity_hierarchy\Storage\Record');
    $type = $this->fieldStorageDefinition->getTargetEntityTypeId();
    array_walk($records, function (Record $record) use ($type) {
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

  public function findRoot(ContentEntityInterface $entity): ?Record {
    $ancestors = $this->findAncestors($entity);
    return reset($ancestors);
  }

  public function findParent(ContentEntityInterface $entity): ?ContentEntityInterface {
    return $entity->get($this->fieldStorageDefinition->getName())->entity;
  }

  public function findDepth(ContentEntityInterface $entity): int {
    $sql = $this->getAncestorSql() . " SELECT depth FROM ancestors ORDER BY depth LIMIT 1";
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

  protected function getDescendantSql(): string {
    $table_name = $this->getTablePrefix() . $this->tables['entity'];
    $column_id = $this->columns['id'];
    $column_revision_id = $this->columns['revision_id'];
    $column_target_id = $this->columns['target_id'];
    $column_weight = $this->columns['weight'];
    $sql = <<<CTESQL
WITH RECURSIVE descendants AS
(
  SELECT $column_id as id, $column_revision_id as revision_id, $column_target_id as target_id, $column_weight as weight, 1 AS depth
  FROM $table_name
  WHERE $column_target_id = :target_id
  UNION ALL
  SELECT c.$column_id, c.$column_revision_id, c.$column_target_id, c.$column_weight, descendants.depth+1
  FROM $table_name c
  JOIN descendants ON descendants.id=c.$column_target_id
)
CTESQL;
    return $sql;
  }

  /**
   * @return \Drupal\entity_hierarchy\Storage\Record[]
   */
  public function findDescendants(ContentEntityInterface $entity, int $depth = 0, int $start = 1) {
    $sql = $this->getDescendantSql() . " SELECT id, revision_id, target_id, weight, depth FROM descendants WHERE depth >= :start";
    $params = [
      ':target_id' => $entity->id(),
      ':start' => $start,
    ];
    if ($depth > 0) {
      $sql .= " AND depth < :depth";
      $params[':depth'] = $start + $depth;
    }
    $sql .= " ORDER by weight, id";
    $result = $this->database->query($sql, $params);
    $records = $result->fetchAll(PDO::FETCH_CLASS, '\Drupal\entity_hierarchy\Storage\Record');
    $type = $this->fieldStorageDefinition->getTargetEntityTypeId();
    array_walk($records, function (Record $record) use ($type) {
      $record->setType($type);
    });
    return $records;
  }

  /**
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *
   * @return \Drupal\entity_hierarchy\Storage\Record[]
   */
  public function findChildren(ContentEntityInterface $entity): array {
    return $this->findDescendants($entity, 1);
  }

}
