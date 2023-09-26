<?php

namespace Drupal\entity_hierarchy\Storage;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\TableMappingInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Psr\Log\LoggerInterface;

class EntityHierarchyQueryBuilder {

  public function __construct(
    protected FieldStorageDefinitionInterface $fieldStorageDefinition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
    protected LoggerInterface $logger
  ) {}

  private function getTableMapping(): TableMappingInterface {
    return $this->entityTypeManager->getStorage($this->fieldStorageDefinition->getTargetEntityTypeId())->getTableMapping();
  }

  private function getTableName(): string {
    return $this->getTableMapping()->getFieldTableName($this->fieldStorageDefinition->getName());
  }

  private function getPropertyColumnName(string $property): string {
    return $this->getTableMapping()->getFieldColumnName($this->fieldStorageDefinition, $property);
  }

  public function findParent(ContentEntityInterface $entity):? ContentEntityInterface {
    return $entity->get($this->fieldStorageDefinition->getName())->entity;
  }

  public function populateEntities(&$records) {
    foreach ($records as $record) {
      // @todo Optimise loading.
      $record->entity = $this->entityTypeManager->getStorage($this->fieldStorageDefinition->getTargetEntityTypeId())->load($record->entity_id);
    }
    return $records;
  }

  public function getEntities($records) {
    $new_records = [];
    foreach ($records as $record) {
      // @todo Optimise loading.
      if ($entity = $this->entityTypeManager->getStorage($this->fieldStorageDefinition->getTargetEntityTypeId())->load($record->entity_id)) {
        $new_records[] = $entity;
      }
    }
    return $new_records;
  }

  public function findChildren(ContentEntityInterface $entity) {
    $column_weight = $this->getPropertyColumnName('weight');
    $query = $this->database->select($this->getTableName(), 'eh');
    $query->addField('eh', 'entity_id');
    $query->addField('eh', $column_weight, 'weight');
    $result = $query->condition($this->getPropertyColumnName('target_id'), $entity->id())
      ->orderBy($column_weight)
      ->execute();
    $records = [];
    foreach ($result as $record) {
      $records[] = $record;
    }
    return $records;
  }

  protected function getAncestorSql(): string {
    // @todo Fix entity_id reference.
    $table_name = $this->getTableName();
    $column_target_id = $this->getPropertyColumnName('target_id');
    $column_entity_id = 'entity_id';
    $sql = <<<CTESQL
WITH RECURSIVE cte AS
(
  SELECT $column_entity_id, $column_target_id, 0 as depth FROM {node_revision__field_parent} WHERE entity_id = :entity_id and revision_id = :revision_id
  UNION ALL
  SELECT c.$column_entity_id, c.$column_target_id, cte.depth-1 FROM {$table_name} c
  JOIN cte ON c.$column_entity_id=cte.$column_target_id
) 
CTESQL;
    return $sql;
  }

  public function findRoot(ContentEntityInterface $entity): ?ContentEntityInterface {
    $column_target_id = $this->getPropertyColumnName('target_id');
    $sql = $this->getAncestorSql() . "SELECT $column_target_id FROM cte ORDER BY depth LIMIT 1";
    $result = $this->database->query($sql, [
      ':entity_id' => $entity->id(),
      ':revision_id' => $entity->getRevisionId(),
    ]);
    $id = $result->fetchObject()->$column_target_id;
    return $id ? $this->entityTypeManager->getStorage($this->fieldStorageDefinition->getTargetEntityTypeId())->load($id) : NULL;
  }

  public function findDepth(ContentEntityInterface $entity): ?int {
    $sql = $this->getAncestorSql() . "SELECT depth FROM cte ORDER BY depth LIMIT 1";
    $result = $this->database->query($sql, [
      ':entity_id' => $entity->id(),
      ':revision_id' => $entity->getRevisionId(),
    ]);
    if ($depth = $result->fetchObject()?->depth) {
      return $depth * -1;
    }
    return NULL;
  }

  public function findAncestors(ContentEntityInterface $entity) {
    $column_target_id = $this->getPropertyColumnName('target_id');
    $sql = $this->getAncestorSql() . "SELECT $column_target_id as entity_id, depth FROM cte ORDER BY depth";
    $result = $this->database->query($sql, [
      ':entity_id' => $entity->id(),
      ':revision_id' => $entity->getRevisionId(),
    ]);
    $records = [];
    foreach ($result as $record) {
      $records[] = $record;
    }
    return $records;
  }

  protected function getDescendantSql(): string {
    // @todo Fix entity_id reference.
    $table_name = $this->getTableName();
    $column_target_id = $this->getPropertyColumnName('target_id');
    $column_entity_id = 'entity_id';
    $sql = <<<CTESQL
WITH RECURSIVE cte AS
(
  SELECT $column_entity_id, $column_target_id, revision_id, CAST($column_target_id AS CHAR(200)) AS path, 1 AS depth
  FROM {$table_name}
  WHERE $column_target_id = :entity_id
  UNION ALL
  SELECT c.$column_entity_id, c.$column_target_id, c.revision_id, CONCAT(cte.path, ',', c.$column_target_id), cte.depth+1
  FROM {$table_name} c
  JOIN cte ON cte.$column_entity_id=c.$column_target_id
) 
CTESQL;
    return $sql;
  }

  public function findDescendants(ContentEntityInterface $entity, int $depth = 0, int $start = 1) {
    $column_entity_id = 'entity_id';
    $sql = $this->getDescendantSql() . "SELECT $column_entity_id, revision_id, path, depth FROM cte WHERE depth >= :start";
    $params = [
      ':entity_id' => $entity->id(),
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