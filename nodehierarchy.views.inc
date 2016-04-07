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
  $data['nodehierarchy'] = array();
  $data['nodehierarchy']['table'] = array();

  // Basic table information.
  $data['nodehierarchy']['table']['group']  = t('Hierarchy');
//  $data['nodehierarchy']['table']['base'] = array(
//    // Identifier (primary) field in this table for Views.
//      'field' => 'nid',
//    // Label in the UI.
//      'title' => t('Hierarchy table'),
//    // Longer description in the UI. Required.
//      'help' => t('Hierarchy table contains the name of the views to be embedded'),
//      'weight' => -10,
//  );
  $data['nodehierarchy']['table']['join'] = array(
    'node' => array(
      'left_field' => 'nid',
      'field' => 'cnid',
    ),
  );

  $data['nodehierarchy']['cweight'] = array(
    'title' => t('Child Weight'),
    'help' => t('The sort order of the child node.'),
    'field' => array(
      'id' => 'cweight',
//      'click sortable' => TRUE,
    ),
    'sort' => array(
      'id' => 'child_weight',
    ),
  );

  $data['nodehierarchy']['pnid'] = array(
    'title' => t('Parent Node ID'),
    'help' => t('The parent id of the node.'),
    // Information for accepting a parent as an argument.
    'argument' => array(
      'field' => 'pnid',
      'handler' => 'views_handler_argument_node_nid',
      'click sortable' => TRUE,
    ),
    'relationship' => array(
      'base' => 'node',
      'base field' => 'nid',
      'field' => 'pnid',
      'handler' => 'views_handler_relationship',
      'label' => t('Parent Node'),
    ),
  );

//  kint($data);

  return $data;
}
