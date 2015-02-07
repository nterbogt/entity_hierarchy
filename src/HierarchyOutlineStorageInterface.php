<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\HierarchyOutlineStorageInterface.
*/

namespace Drupal\nodehierarchy;

/**
 * Defines a common interface for hierarchy outline storage classes.
 */
interface HierarchyOutlineStorageInterface {

  /**
   * Deletes a hierarchy entry.
   *
   * @param int $nid
   *   Deletes a hierarchy entry.
   *
   * @return mixed
   *   Number of deleted hierarchy entries.
   */
  public function delete($nid);


  /**
   * Inserts a hierarchy link.
   *
   * @param object $item (TODo: change to array)
   *   The link object to be inserted in the database.
   * @param array $parents
   *   The array of parent ids for the link to be inserted.
   *
   * @return mixed
   *   The last insert ID of the query, if one exists.
   */
  public function insert($item/*, $parents*/);


  /**
   * Updates hierarchy reference for links that were moved between hierarchies.
   *
   * @param int $nid
   *   The nid of the hierarchy entry to be updated.
   * @param array $fields
   *   The array of fields to be updated.
   *
   * @return mixed
   *   The number of rows matched by the update query.
   */
  public function update($nid, $fields);



}
