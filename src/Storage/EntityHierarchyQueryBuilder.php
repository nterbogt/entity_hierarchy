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

  public function findChildren(ContentEntityInterface $entity) {
    $column_weight = $this->getPropertyColumnName('weight');
    $result = $this->database->select($this->getTableName(), 'eh')
      ->fields('eh', ['entity_id', $column_weight])
      ->condition($this->getPropertyColumnName('target_id'), $entity->id())
      ->orderBy($column_weight)
      ->execute();
    $children = [];
    foreach ($result as $record) {
      // @todo Optimise loading.
      $children[$record->$column_weight] = $this->entityTypeManager->getStorage($this->fieldStorageDefinition->getTargetEntityTypeId())->load($record->entity_id);
    }
    return $children;
  }

  protected function getAncestorSql(): string {
    // @todo Fix entity_id reference.
    $table_name = $this->getTableName();
    $column_target_id = $this->getPropertyColumnName('target_id');
    $column_entity_id = 'entity_id';
    $sql = <<<CTESQL
WITH RECURSIVE cte AS
(
  SELECT $column_entity_id, $column_target_id, 0 as depth FROM {$table_name} WHERE entity_id = :entity_id
  UNION ALL
  SELECT c.$column_target_id, c.$column_target_id, cte.depth-1 FROM {$table_name} c JOIN cte
  ON c.$column_entity_id=cte.$column_target_id
) 
CTESQL;
    return $sql;
  }

  public function findRoot(ContentEntityInterface $entity): ?ContentEntityInterface {
    $column_target_id = $this->getPropertyColumnName('target_id');
    $sql = $this->getAncestorSql() . "SELECT $column_target_id FROM cte ORDER BY depth";
    $result = $this->database->query($sql, [
      ':entity_id' => $entity->id()
    ]);
    $id = $result->fetchObject()->$column_target_id;
    return $id ? $this->entityTypeManager->getStorage($this->fieldStorageDefinition->getTargetEntityTypeId())->load($id) : NULL;
  }

  public function findAncestors(ContentEntityInterface $entity) {
    $column_target_id = $this->getPropertyColumnName('target_id');
    $sql = $this->getAncestorSql() . "SELECT $column_target_id FROM cte ORDER BY depth LIMIT 1";
    $result = $this->database->query($sql, [
      ':entity_id' => $entity->id()
    ]);
    $ancestors = [];
    foreach ($result as $record) {
      // @todo Optimise loading.
      $ancestors[] = $this->entityTypeManager->getStorage($this->fieldStorageDefinition->getTargetEntityTypeId())->load($record->$column_target_id);
    }
    return $ancestors;
  }

}