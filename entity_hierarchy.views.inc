<?php
/**
 * @file
 * Views hooks for entity_hierarchy_views.
 */

/**
 * Implements hook_views_data().
 */
function entity_hierarchy_views_data() {
  $data = [];
  $entityTypeManager = \Drupal::service('entity_type.manager');
  /** @var \Drupal\Core\Entity\EntityFieldManager $fieldManager */
  $fieldManager = \Drupal::service('entity_field.manager');
  foreach ($fieldManager->getFieldMapByFieldType('entity_reference_hierarchy') as $entity_type_id => $fields) {
    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
    $entity_type = $entityTypeManager->getDefinition($entity_type_id);
    foreach ($fields as $field_name => $field_info) {
      $table_name = \Drupal::service('entity_hierarchy.nested_set_storage_factory')->getTableName($field_name, $entity_type_id, FALSE);
      $sample_bundle = reset($field_info['bundles']);
      /** @var Drupal\Core\Field\FieldDefinitionInterface $sample_field */
      $sample_field = $fieldManager->getFieldDefinitions($entity_type_id, $sample_bundle)[$field_name];
      $data[$table_name]['table']['group']  = t('Entity hierarchy: @entity_type - @field_name', [
        '@entity_type' => $entity_type->getLabel(),
        '@field_name' => $sample_field->getLabel(),
      ]);
      $base_table = $entity_type->getDataTable() ?: $entity_type->getBaseTable();

      $data[$table_name]['table']['join'] = [
        $base_table => [
          'left_field' => $entity_type->getKey('id'),
          'field' => 'id',
        ],
      ];
      if ($revision_key = $entity_type->hasKey('revision')) {
        $data[$table_name]['table']['join'][$base_table]['left_field'] = $revision_key;
        $data[$table_name]['table']['join'][$base_table]['field'] = 'revision_id';
      }
      $data[$table_name]['left_pos'] = [
        'title' => t('Hierarchy order'),
        'help' => t('Sort in hierarchy order'),
        'sort' => [
          'id' => 'standard',
        ],
      ];
      $data[$table_name]['is_child'] = [
        'title' => t('Hierarchy: Is child of'),
        'help' => t('Limit to children of given entity'),
        'real field' => 'left_pos',
        'argument' => [
          'id' => $revision_key ? 'entity_hierarchy_argument_is_child_of_entity_revision' : 'entity_hierarchy_argument_is_child_of_entity',
        ],
      ];
      $data[$table_name]['depth'] = [
        'title' => t('Hierarchy depth'),
        'help' => t('Depth in hierarchy'),
        'sort' => [
          'id' => 'standard',
        ],
        'argument' => [
          'id' => 'standard',
          'numeric' => TRUE,
        ],
      ];
      $data[$base_table]['tree'] = [
        'title' => t('Entity hierarchy'),
        'help' => t('The hierarchy information'),
        'real field' => $revision_key ?: $entity_type->getKey('id'),
        'group' => t('Entity hierarchy'),
        'relationship' => [
          'title' => 'Entity Hierarchy',
          'help' => 'Relate to hierarchy information',
          'id' => 'standard',
          'base' => $table_name,
          'base field' => $revision_key ? 'revision_id' : 'id',
          'field' => $revision_key ?: $entity_type->getKey('id'),
        ],
      ];
    }
  }

  return $data;
}
