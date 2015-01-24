<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\HierarchyOutline.
 */

namespace Drupal\nodehierarchy;

/**
 * Provides handling to render the hierarchy outline.
 */
class HierarchyOutline {

  /**
   * The hierarchy manager.
   *
   * @var \Drupal\hierarchy\HierarchyManagerInterface
   */
  protected $hierarchyManager;

  /**
   * Constructs a new HierarchyOutline.
   *
   * @param \Drupal\hierarchy\HierarchyManagerInterface $hierarchy_manager
   *   The hierarchy manager.
   */
  public function __construct(HierarchyManagerInterface $hierarchy_manager) {
    $this->hierarchyManager = $hierarchy_manager;
  }

  /**
   * Fetches the hierarchy link for the previous page of the hierarchy.
   *
   * @param array $hierarchy_link
   *   A fully loaded hierarchy link that is part of the hierarchy hierarchy.
   *
   * @return array
   *   A fully loaded hierarchy link for the page before the one represented in
   *   $hierarchy_link.
   */
  public function prevLink(array $hierarchy_link) {
    // If the parent is zero, we are at the start of a hierarchy.
    if ($hierarchy_link['pid'] == 0) {
      return NULL;
    }
    // Assigning the array to $flat resets the array pointer for use with each().
    $flat = $this->hierarchyManager->hierarchyTreeGetFlat($hierarchy_link);
    $curr = NULL;
    do {
      $prev = $curr;
      list($key, $curr) = each($flat);
    } while ($key && $key != $hierarchy_link['nid']);

    if ($key == $hierarchy_link['nid']) {
      // The previous page in the hierarchy may be a child of the previous visible link.
      if ($prev['depth'] == $hierarchy_link['depth']) {
        // The subtree will have only one link at the top level - get its data.
        $tree = $this->hierarchyManager->hierarchySubtreeData($prev);
        $data = array_shift($tree);
        // The link of interest is the last child - iterate to find the deepest one.
        while ($data['below']) {
          $data = end($data['below']);
        }
        $this->hierarchyManager->hierarchyLinkTranslate($data['link']);
        return $data['link'];
      }
      else {
        $this->hierarchyManager->hierarchyLinkTranslate($prev);
        return $prev;
      }
    }
  }

  /**
   * Fetches the hierarchy link for the next page of the hierarchy.
   *
   * @param array $hierarchy_link
   *   A fully loaded hierarchy link that is part of the hierarchy hierarchy.
   *
   * @return array
   *   A fully loaded hierarchy link for the page after the one represented in
   *   $hierarchy_link.
   */
  public function nextLink(array $hierarchy_link) {
    // Assigning the array to $flat resets the array pointer for use with each().
    $flat = $this->hierarchyManager->hierarchyTreeGetFlat($hierarchy_link);
    do {
      list($key,) = each($flat);
    } while ($key && $key != $hierarchy_link['nid']);

    if ($key == $hierarchy_link['nid']) {
      $next = current($flat);
      if ($next) {
        $this->hierarchyManager->hierarchyLinkTranslate($next);
      }
      return $next;
    }
  }

  /**
   * Formats the hierarchy links for the child pages of the current page.
   *
   * @param array $hierarchy_link
   *   A fully loaded hierarchy link that is part of the hierarchy hierarchy.
   *
   * @return array
   *   HTML for the links to the child pages of the current page.
   */
  public function childrenLinks(array $hierarchy_link) {
    $flat = $this->hierarchyManager->hierarchyTreeGetFlat($hierarchy_link);

    $children = array();

    if ($hierarchy_link['has_children']) {
      // Walk through the array until we find the current page.
      do {
        $link = array_shift($flat);
      } while ($link && ($link['nid'] != $hierarchy_link['nid']));
      // Continue though the array and collect the links whose parent is this page.
      while (($link = array_shift($flat)) && $link['pid'] == $hierarchy_link['nid']) {
        $data['link'] = $link;
        $data['below'] = '';
        $children[] = $data;
      }
    }

    if ($children) {
      $elements = $this->hierarchyManager->hierarchyTreeOutput($children);
      return drupal_render($elements);
    }
    return '';
  }

}
