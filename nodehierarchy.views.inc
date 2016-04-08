<?php
/**
 * @file
 * Views hooks for nodehierarchy_views.
 */

/**
 * Implements hook_views_data().
 */
function nodehierarchy_views_data() {

  $data = array();
  $data['nodehierarchy']['table']['group']  = t('Hierarchy');
  $data['nodehierarchy']['table']['join'] = array(
    'node_field_data' => array(
      'left_field' => 'nid',
      'field' => 'cnid',
    ),
  );

  $data['nodehierarchy']['cweight'] = array(
    'title' => t('Child Weight'),
    'help' => t('The sort order of the child node.'),
    'field' => array(
      'id' => 'numeric',
    ),
    'sort' => array(
      'id' => 'standard',
    ),
  );

  $data['nodehierarchy']['pnid'] = array(
    'title' => t('Parent Node ID'),
    'help' => t('The parent id of the node.'),
    // Information for accepting a parent as an argument.
    'argument' => array(
      'field' => 'pnid',
      'id' => 'numeric',
    ),
    'relationship' => array(
      'base' => 'node_field_data',
      'base field' => 'nid',
      'field' => 'pnid',
      'id' => 'standard',
      'label' => t('Parent Node'),
    ),
  );
  
  return $data;
}
