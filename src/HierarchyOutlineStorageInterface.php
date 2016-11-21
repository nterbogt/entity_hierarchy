<?php

/**
 * @file
 * Contains \Drupal\entity_hierarchy\HierarchyOutlineStorageInterface.
*/

namespace Drupal\entity_hierarchy;

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

  public function hierarchyNodesByType($types);

  public function hierarchyLoadParentNextChildWeight($pnid);

  public function hierarchyRecordLoad($hid);

  public function hierarchyRecordDelete($hid);

  public function loadHierarchies($nids);
}
