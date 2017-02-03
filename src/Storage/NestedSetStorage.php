<?php

namespace Drupal\entity_hierarchy\Storage;
use Doctrine\DBAL\Connection;
use PNX\NestedSet\Storage\DbalNestedSet;
use PNX\NestedSet\Storage\DbalNestedSetSchema;

/**
 * Wraps the library nested set implementation with JIT table creation.
 */
class NestedSetStorage {

  /**
   * Schema for storage.
   *
   * @var \PNX\NestedSet\Storage\DbalNestedSetSchema
   */
  protected $schema;

  /**
   * Proxy for storage.
   *
   * @var \PNX\NestedSet\Storage\DbalNestedSet
   */
  protected $proxy;

  /**
   * Constructs a new NestedSetStorage object.
   *
   * @param \Doctrine\DBAL\Connection $connection
   *   Connection.
   * @param string $table_name
   *   Table name.
   */
  function __construct(Connection $connection, $table_name) {
    $this->schema = new DbalNestedSetSchema($connection, $table_name);
    $this->proxy = new DbalNestedSet($connection, $table_name);
  }

  /**
   * {@inheritdoc}
   */
  public function __call($name, $arguments) {
    $try_again = FALSE;
    try {
      return $this->doCall($name, $arguments);
    }
    catch (\Exception $e) {
      // If there was an exception, try to create the table.
      if (!$try_again = $this->ensureTableExists()) {
        // If the exception happened for other reason than the missing table,
        // propagate the exception.
        throw $e;
      }
    }
    // Now that the table has been created, try again if necessary.
    if ($try_again) {
      return $this->doCall($name, $arguments);
    }
    throw new \LogicException('Unexpected exception occurred.');
  }

  /**
   * @param $name
   * @param $arguments
   * @return mixed
   */
  protected function doCall($name, $arguments) {
    return call_user_func_array([$this->proxy, $name], $arguments);
  }

  /**
   * Creates the table if required.
   */
  protected function ensureTableExists() {
    try {
      $this->schema->create();
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

}
