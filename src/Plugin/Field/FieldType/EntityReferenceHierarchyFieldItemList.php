<?php

declare(strict_types=1);

namespace Drupal\entity_hierarchy\Plugin\Field\FieldType;

use Drupal\Core\Field\EntityReferenceFieldItemList;

/**
 * Defines a field item list for entity reference with hierarchy.
 */
class EntityReferenceHierarchyFieldItemList extends EntityReferenceFieldItemList {

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();
    $constraint_manager = $this->getTypedDataManager()->getValidationConstraintManager();
    $constraints[] = $constraint_manager->create('ValidHierarchyReference', []);
    return $constraints;
  }

}
