<?php

/**
 * @file
 * Definition of Drupal\nodehierarchy\HierarchyOutlineStorage.
 */

namespace Drupal\nodehierarchy;

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

  /**
   * Get all the parents for the given node.
   */
  public function hierarchyGetParents($node, $limit = NULL) {
    $cnid = $node;

    // If a node object was passed, then the parents may already have been loaded.
    if (is_object($node)) {
      if (isset($node->nodehierarchy_parents)) {
        return $node->nodehierarchy_parents;
      }
      $cnid = $node->nid;
    }

    $out = array();

    $query = db_select('nodehierarchy', 'nh')
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
   * {@inheritdoc}
   */
  public function getHierarchies() {
//    return $this->connection->query("SELECT DISTINCT(hid) FROM {hierarchy}")->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function hasHierarchies() {
//    return (bool) $this->connection
//      ->query('SELECT count(hid) FROM {hierarchy}')
//      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple($nids) {
//    $query = $this->connection->select('hierarchy', 'nh', array('fetch' => \PDO::FETCH_ASSOC));
//    $query->fields('nh');
//    $query->condition('nh.nid', $nids);
//    $query->addTag('node_access');
//    $query->addMetaData('base_table', 'hierarchy');
//    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getChildRelativeDepth($hierarchy_link, $max_depth) {
//    $query = $this->connection->select('hierarchy');
//    $query->addField('hierarchy', 'depth');
//    $query->condition('hid', $hierarchy_link['hid']);
//    $query->orderBy('depth', 'DESC');
//    $query->range(0, 1);
//
//    $i = 1;
//    $p = 'p1';
//    while ($i <= $max_depth && $hierarchy_link[$p]) {
//      $query->condition($p, $hierarchy_link[$p]);
//      $p = 'p' . ++$i;
//    }
//
//    return $query->execute()->fetchField();
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
   * {@inheritdoc}
   */
  public function loadHierarchyChildren($pid) {
//    return $this->connection
//      ->query("SELECT * FROM {hierarchy} WHERE pid = :pid", array(':pid' => $pid))
//      ->fetchAllAssoc('nid', \PDO::FETCH_ASSOC);
  }

  /**
   * {@inheritdoc}
   */
  public function getHierarchyMenuTree($hid, $parameters, $min_depth, $max_depth) {
//    $query = $this->connection->select('hierarchy');
//    $query->fields('hierarchy');
//    for ($i = 1; $i <= $max_depth; $i++) {
//      $query->orderBy('p' . $i, 'ASC');
//    }
//    $query->condition('hid', $hid);
//    if (!empty($parameters['expanded'])) {
//      $query->condition('pid', $parameters['expanded'], 'IN');
//    }
//    if ($min_depth != 1) {
//      $query->condition('depth', $min_depth, '>=');
//    }
//    if (isset($parameters['max_depth'])) {
//      $query->condition('depth', $parameters['max_depth'], '<=');
//    }
//    // Add custom query conditions, if any were passed.
//    if (isset($parameters['conditions'])) {
//      foreach ($parameters['conditions'] as $column => $value) {
//        $query->condition($column, $value);
//      }
//    }
//
//    return $query->execute();
  }


  /**
   * Get the next child weight for a given pnid.
   */
  public function hierarchyLoadParentNextChildWeight($pnid) {
    $out = db_query('SELECT MAX(cweight) FROM {nodehierarchy} WHERE pnid = :pnid', array(':pnid' => $pnid))->fetchField();
    if ($out !== NULL) {
      $out += 1;
    }
    else {
      $out = 0;
    }
    return $out;
  }

  /**
   * Save a nodehierarchy record.
   */
  public function hierarchyRecordLoad($hid) {
    $result = db_select('nodehierarchy', 'nh')
      ->fields('nh')
      ->where('hid = :hid', array(':hid' => $hid))->execute();
    return $result->fetch();
  }

  /**
   * Save a nodehierarchy record.
   */
  public function hierarchyRecordDelete($hid) {
    db_delete('nodehierarchy')->condition('hid', $hid)->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function insert($item) {

    return $this->connection->insert('nodehierarchy')
      ->fields(array(
//        'hid' => $item->nhid,  // hid set automatically (primary key)
        'pnid' => $item->pnid,
//        'cnid' => $item->cnid,
        'cweight' => $item->cweight,
        )
      )
      ->execute();
  }

  /**
   * Count the children of the given node.
   */
  function hierarchyGetNodeChildrenCount($node) {
    $pnid = $node;
    if (is_object($node)) {
      $pnid = $node->nid;
    }

    return (int)db_query("SELECT count(*) FROM {nodehierarchy} WHERE pnid = :pnid", array(':pnid' => $pnid))->fetchField();
  }

  /**
   * Get the children of the given node.
   */
  public function hierarchyGetNodeChildren($node, $limit = FALSE) {
    $pnid = $node;
    if (is_object($node)) {
      $pnid = $node->nid;
    }

    $query = db_select('nodehierarchy', 'nh')
      ->fields('nh')
//      ->fields('n', array('title'))
      ->where('pnid = :pnid', array(':pnid' => $pnid))
      ->orderBy('cweight', 'ASC');

    $query->leftJoin('node', 'n', 'nh.cnid = n.nid');

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
   * {@inheritdoc}
   */
  public function update($nid, $fields) {
//    return $this->connection
//      ->update('hierarchy')
//      ->fields($fields)
//      ->condition('nid', $nid)
//      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function updateMovedChildren($hid, $original, $expressions, $shift) {
//    $query = $this->connection->update('hierarchy');
//    $query->fields(array('hid' => $hid));
//
//    foreach ($expressions as $expression) {
//      $query->expression($expression[0], $expression[1], $expression[2]);
//    }
//
//    $query->expression('depth', 'depth + :depth', array(':depth' => $shift));
//    $query->condition('hid', $original['hid']);
//    $p = 'p1';
//    for ($i = 1; !empty($original[$p]); $p = 'p' . ++$i) {
//      $query->condition($p, $original[$p]);
//    }
//
//    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function countOriginalLinkChildren($original) {
//    return $this->connection->select('hierarchy', 'b')
//      ->condition('hid', $original['hid'])
//      ->condition('pid', $original['pid'])
//      ->condition('nid', $original['nid'], '<>')
//      ->countQuery()
//      ->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getHierarchySubtree($link, $max_depth) {
//    $query = db_select('hierarchy', 'nh', array('fetch' => \PDO::FETCH_ASSOC));
//    $query->fields('nh');
//    $query->condition('nh.hid', $link['hid']);
//
//    for ($i = 1; $i <= $max_depth && $link["p$i"]; ++$i) {
//      $query->condition("p$i", $link["p$i"]);
//    }
//    for ($i = 1; $i <= $max_depth; ++$i) {
//      $query->orderBy("p$i");
//    }
//    return $query->execute();
  }
}
