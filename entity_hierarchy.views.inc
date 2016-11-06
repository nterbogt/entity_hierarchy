<?php
/**
 * @file
 * Views hooks for entity_hierarchy_views.
 */

/**
 * Implements hook_views_data().
 */
function entity_hierarchy_views_data() {

  $data = array();
  $data['entity_hierarchy']['table']['group']  = t('Hierarchy');
  $data['entity_hierarchy']['table']['join'] = array(
    'node_field_data' => array(
      'left_field' => 'nid',
      'field' => 'cnid',
    ),
  );

  $data['entity_hierarchy']['cweight'] = array(
    'title' => t('Child Weight'),
    'help' => t('The sort order of the child node.'),
    'field' => array(
      'id' => 'numeric',
    ),
    'sort' => array(
      'id' => 'standard',
    ),
  );

  $data['entity_hierarchy']['pnid'] = array(
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
