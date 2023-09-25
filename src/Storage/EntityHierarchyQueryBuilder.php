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

  public function findChildren(ContentEntityInterface $entity) {
    // @todo Fix entity ID reference.
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

}