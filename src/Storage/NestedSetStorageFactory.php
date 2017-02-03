<?php

namespace Drupal\entity_hierarchy\Storage;

use Doctrine\DBAL\Connection;

/**
 * Defines a factory for creating a nested set storage handler for hierarchies.
 */
class NestedSetStorageFactory {

  /**
   * DBAL connection.
   *
   * @var \Doctrine\DBAL\Connection
   */
  protected $connection;

  /**
   * Static cache.
   *
   * @var array
   */
  protected $cache = [];

  /**
   * Constructs a new NestedSetStorageFactory object.
   *
   * @param \Doctrine\DBAL\Connection $connection
   *   Db Connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Gets a new nested set storage handler.
   *
   * @param string $field_name
   *   Field name for storage.
   * @param string $entity_type_id
   *   Entity Type ID.
   *
   * @return \Drupal\entity_hierarchy\Storage\NestedSetStorage
   *   Nested set for given field.
   */
  public function get($field_name, $entity_type_id) {
    $cache_id = sprintf('nested_set_%s_%s', $field_name, $entity_type_id);
    if (!isset($this->cache[$cache_id])) {
      $this->cache[$cache_id] = new NestedSetStorage($this->connection, $cache_id);
    }
    return $this->cache[$cache_id];
  }

}
