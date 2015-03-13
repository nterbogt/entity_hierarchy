<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\HierarchyManagerInterface.
 */

namespace Drupal\nodehierarchy;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;


/**
 * Provides an interface defining a hierarchy manager.
 */
interface HierarchyManagerInterface {

  /**
   * Loads multiple hierarchy entries.
   *
   * The entries of a hierarchy entry is documented in
   * \Drupal\nodehierarchy\HierarchyOutlineStorageInterface::loadHierarchies.
   *
   * @param int[] $nids
   *   An array of nids to load.
   *
   * @return array[]
   *   The parent ID(s)
   *
   * @see \Drupal\nodehierarchy\HierarchyOutlineStorageInterface::loadHierarchies
   */
  public function loadHierarchy($nids);

  public function updateHierarchy($item);

}