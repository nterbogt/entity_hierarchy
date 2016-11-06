<?php

/**
 * @file
 * Definition of Drupal\entity_hierarchy\HierarchyOutlineStorage.
 */

namespace Drupal\entity_hierarchy;

use Drupal\Core\Database\Connection;

/**
 * Defines a storage class for hierarchies outline.
 */
class HierarchyOutlineStorage implements HierarchyOutlineStorageInterface {

  /**
   * Database Service Object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a HierarchyOutlineStorage object.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  public function getHierarchies() {
    return $this->connection->query("SELECT DISTINCT(hid) FROM {entity_hierarchy}")->fetchCol();
  }


  /**
   * Get all the parents for the given node.
   */
  public function hierarchyGetParents($node, $limit = NULL) {
    $cnid = $node;

    // If a node object was passed, then the parents may already have been loaded.
    if (is_object($node)) {
      if (isset($node->entity_hierarchy_parents)) {
        return $node->entity_hierarchy_parents;
      }
      $cnid = $node->nid;
    }

    $out = array();

    $query = db_select('entity_hierarchy', 'nh')
      ->fields('nh')
      ->where('cnid = :cnid', array(':cnid' => $cnid))
      ->orderBy('pweight', 'ASC');

    if ($limit) {
      $query->range(0, $limit);
    }

    $result = $query->execute()->fetchAll();

    foreach ($result as $item) {
      $out[] = $item;
    }
    return $out;
  }

  /**
   * Get the immediate parent for the given node.
   */
  public function hierarchyGetParent($cnid) {

    $query = db_select('entity_hierarchy', 'nh')
      ->fields('nh')
      ->where('cnid = :cnid', array(':cnid' => $cnid))
      ->orderBy('pweight', 'ASC');

    $result = $query->execute()->fetch();
    return $result;
  }


  /**
   * {@inheritdoc}
   */
  public function delete($nid) {
//    return $this->connection->delete('hierarchy')
//      ->condition('nid', $nid)
//      ->execute();
  }


  /**
   * Get the next child weight for a given pnid.
   */
  public function hierarchyLoadParentNextChildWeight($pnid) {
    $out = db_query('SELECT MAX(cweight) FROM {entity_hierarchy} WHERE pnid = :pnid', array(':pnid' => $pnid))->fetchField();
    if ($out !== NULL) {
      $out += 1;
    }
    else {
      $out = 0;
    }
    return $out;
  }

  /**
   * Save a entity_hierarchy record.
   */
  public function hierarchyRecordLoad($hid) {
    $result = db_select('entity_hierarchy', 'nh')
      ->fields('nh')
      ->where('hid = :hid', array(':hid' => $hid))->execute();
    return $result->fetch();
  }

  /**
   * Save a entity_hierarchy record.
   */
  public function hierarchyRecordDelete($hid) {
    db_delete('entity_hierarchy')->condition('hid', $hid)->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function insert($item) {
    return $this->connection->insert('entity_hierarchy')
      ->fields(array(
        'pnid' => $item->pnid,
        'cnid' => $item->cnid,
        'cweight' => $item->cweight,
        'pweight' => $item->pweight,
        )
      )
      ->execute();
  }

  /**
   * Count the children of the given node.
   */
  function hierarchyGetNodeChildrenCount($node) {
//    dsm($node);
    $pnid = $node;
    if (is_object($node)) {
      $pnid = $node->id();
    }

    return (int)db_query("SELECT count(*) FROM {entity_hierarchy} WHERE pnid = :pnid", array(':pnid' => $pnid))->fetchField();
  }

  /**
   * Get the children of the given node.
   */
  public function hierarchyGetNodeChildren($node, $limit = FALSE) {
    $pnid = $node;
    if (is_object($node)) {
      $pnid = $node->id();
    }

    $query = db_select('entity_hierarchy', 'nh')
      ->fields('nh')
      ->fields('nfd', array('title'))
      ->where('pnid = :pnid', array(':pnid' => $pnid))
      ->orderBy('cweight', 'ASC');

    $query->leftJoin('node', 'n', 'nh.cnid = n.nid');
    $query->leftJoin('node_field_data', 'nfd', 'nfd.nid = n.nid');

    if ($limit) {
      $query->range(0, $limit);
    }

    $result = $query->execute()->fetchAll();
    $children = array();
    foreach ($result as $item) {
      $children[] = $item;
    }
    return $children;
  }

/**
* Get a tree of nodes of the given type.
*/
  public function hierarchyNodesByType($types) {
    $out = array();

    if ($types) {
      $query = db_select('node', 'n')
        ->fields('n', array('nid', 'type'))
        ->fields('nh', array('cweight', 'pnid'))
        ->fields('nfd', array('title', 'uid', 'status'))
        ->condition('n.type', $types, 'IN')
        ->orderBy('nh.cweight', 'ASC');
      $query->leftJoin('entity_hierarchy', 'nh', 'nh.cnid = n.nid');
      $query->leftJoin('node_field_data', 'nfd', 'nfd.nid = n.nid');

      $result = $query->execute();
      foreach ($result as $item) {
        $out[$item->nid] = $item;
      }
    }

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function update($hid, $fields) {
    return $this->connection
      ->update('entity_hierarchy')
      ->fields($fields)
      ->condition('hid', $hid)
      ->execute();
  }

  public function loadHierarchies($nids) {
    $results = db_select('entity_hierarchy', 'h')
      ->fields('h', array('pnid'))
      ->condition('h.cnid', $nids, 'IN')
      ->execute()
      ->fetchAllAssoc('pnid', \PDO::FETCH_ASSOC);
    $pnids = array();
    foreach ($results as $result) {
      $pnids[] = $result['pnid'];
    }
    return $pnids;
  }

}
