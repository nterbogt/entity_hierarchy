<?php

namespace Drupal\entity_hierarchy\Information;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a class for determining if an entity is a parent candidate.
 */
class ParentCandidate implements ParentCandidateInterface {

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a new ReorderChildrenAccess object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity field manager.
   */
  public function __construct(EntityFieldManagerInterface $entityFieldManager) {
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getCandidateFields(EntityInterface $entity) {
    $fields = $this->entityFieldManager->getFieldMapByFieldType('entity_reference_hierarchy');
    $valid_fields = [];
    $entity_type = $entity->getEntityTypeId();
    if (isset($fields[$entity_type])) {
      // See if any bundles point to this entity.
      // We only consider this entity type, there is no point in a hierarchy
      // that spans entity-types as you cannot have more than a single level.
      foreach ($fields[$entity_type] as $field_name => $detail) {
        foreach ($detail['bundles'] as $bundle) {
          /** @var \Drupal\Core\Field\FieldDefinitionInterface $field */
          $field = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle)[$field_name];
          $settings = $field->getSetting('handler_settings');
          if (!isset($settings['target_bundles']) || in_array($entity->bundle(), $settings['target_bundles'], TRUE)) {
            // No target bundles means any can be referenced, return early.
            $valid_fields[] = $field_name;
            continue 2;
          }
        }
      }
    }
    return $valid_fields;
  }

}
