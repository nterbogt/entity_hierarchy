<?php

declare(strict_types=1);

namespace Drupal\entity_hierarchy\Storage;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Defines a factory for creating a nested set storage handler for hierarchies.
 */
class QueryBuilderFactory {

  /**
   * Static cache.
   *
   * @var array
   */
  protected $cache = [];

  /**
   * Constructs a new QueryBuilderFactory object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
  public function __construct(
    protected readonly Connection $database,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly LoggerInterface $logger,
    protected readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Gets a new hierarchy query builder.
   *
   * @param string $field_name
   *   Field name for storage.
   * @param string $entity_type_id
   *   Entity Type ID.
   *
   * @return \Drupal\entity_hierarchy\Storage\QueryBuilder
   *   Handler for making requests to the database based on hierarchy.
   */
  public function get(string $field_name, string $entity_type_id): QueryBuilder {
    $cache_key = "$field_name:$entity_type_id";
    if (!isset($this->cache[$cache_key])) {
      $field_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      $field_storage = $field_definitions[$field_name];
      $this->cache[$cache_key] = new QueryBuilder($field_storage, $this->entityTypeManager, $this->database, $this->logger);
    }
    return $this->cache[$cache_key];
  }

}
