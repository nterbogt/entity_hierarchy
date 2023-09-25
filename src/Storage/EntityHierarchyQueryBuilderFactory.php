<?php

namespace Drupal\entity_hierarchy\Storage;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Defines a factory for creating a nested set storage handler for hierarchies.
 */
class EntityHierarchyQueryBuilderFactory {

  /**
   * Static cache.
   *
   * @var array
   */
  protected $cache = [];

  /**
   */
  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $logger,
    protected EntityFieldManagerInterface $entityFieldManager
  ) {}

  /**
   * Gets a new hierarchy query builder.
   *
   * @param string $field_name
   *   Field name for storage.
   * @param string $entity_type_id
   *   Entity Type ID.
   *
   * @return \Drupal\entity_hierarchy\Storage\EntityHierarchyQueryBuilder
   *   Handler for making requests to the database based on hierarchy.
   */
  public function get($field_name, $entity_type_id) {
    $cache_key = "$field_name:$entity_type_id";
    if (!isset($this->cache[$cache_key])) {
      $field_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      $field_storage = $field_definitions[$field_name];
      $this->cache[$cache_key] = new EntityHierarchyQueryBuilder($field_storage, $this->entityTypeManager, $this->database, $this->logger);
    }
    return $this->cache[$cache_key];
  }

}
