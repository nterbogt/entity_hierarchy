<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy_views\Plugin\views\sort\ChildWeight.
 */

namespace Drupal\nodehierarchy_views\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Sort handler for hierarchy children
 *
 * @ingroup views_sort_handlers
 *
 * @ViewsSort("child_weight")
 */
class ChildWeight extends SortPluginBase {
  protected function defineOptions() {
    $options = parent::defineOptions();
//    kint($options);
    return $options;
  }
  public function query() {
    $this->ensureMyTable();
  }
}
