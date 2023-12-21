<?php

/**
 * @file
 * Views hooks for entity_hierarchy_views.
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
function entity_hierarchy_field_views_data(FieldStorageConfigInterface $field_storage) {
  $data = views_field_default_views_data($field_storage);
  $entityTypeManager = \Drupal::entityTypeManager();
  $entity_type_id = $field_storage->getTargetEntityTypeId();
  $table_mapping = $entityTypeManager->getStorage($entity_type_id)->getTableMapping();
  $field_name = $field_storage->getName();

  if (!$entityTypeManager->hasHandler($entity_type_id, 'views_data')) {
    return [];
  }

  // Identify all the target entity type ids that can be referenced.
  foreach ($data as $table_name => $table_data) {
    // Add a relationship to all the target entity types.
    $entity_type = $entityTypeManager->getDefinition($entity_type_id);
    $target_base_table = $entityTypeManager->getHandler($entity_type_id, 'views_data')
      ->getViewsTableForEntityType($entity_type);

    $args = [
      '@label' => $entity_type->getLabel(),
      '@field_name' => $field_name,
    ];

    // Summarises the children for admin usage.
    $data[$table_name]["{$field_name}__entity_hierarchy_summary"] = [
      'title' => t('@field_name - Child summary', $args),
      'label' => t('Child summary'),
      'group' => t('Entity hierarchy'),
      'help' => t('Administrative field to show information about the children.'),
      'field' => [
        'id' => 'entity_hierarchy_tree_summary',
        'entity_hierarchy_field' => $field_name,
      ],
    ];
    // Get the depth for an entity.
    $data[$table_name]["{$field_name}__entity_hierarchy_depth"] = [
      'title' => t('@field_name - Depth', $args),
      'label' => t('Depth'),
      'group' => t('Entity hierarchy'),
      'help' => t('The depth of the entity in the hierarchy.'),
      'field' => [
        'id' => 'entity_hierarchy_depth',
        'entity_hierarchy_field' => $field_name,
      ],
    ];

    // Normal entity reference to parent.
    $data[$table_name]["{$entity_type_id}__{$field_name}"]['relationship'] = [
      'title' => t('@field_name - Parent', $args),
      'label' => t('Parent', $args),
      'group' => t('Entity hierarchy'),
      'help' => t('The parent of this content via @field_name.', $args),
      'id' => 'standard',
      'base' => $target_base_table,
      'base field' => $entity_type->getKey('id'),
      'entity type' => $entity_type_id,
      'relationship field' => $field_name . '_target_id',
    ];

    // Reverse entity reference to children.
    $data[$target_base_table]["reverse__{$entity_type_id}__{$field_name}"]['relationship'] = [
      'title' => t('@field_name - Children', $args),
      'label' => t('Children'),
      'group' => t('Entity hierarchy'),
      'help' => t('Children that refer to this content via @field_name.', $args),
      'id' => 'entity_reverse',
      'base' => $entityTypeManager->getHandler($entity_type_id, 'views_data')
        ->getViewsTableForEntityType($entity_type),
      'entity_type' => $entity_type_id,
      'base field' => $entity_type->getKey('id'),
      'field_name' => $field_name,
      'field table' => $table_mapping->getDedicatedDataTableName($field_storage),
      'field field' => $field_name . '_target_id',
    ];
  }

  return $data;
}
