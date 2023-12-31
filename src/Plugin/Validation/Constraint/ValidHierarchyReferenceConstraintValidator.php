<?php

declare(strict_types=1);

namespace Drupal\entity_hierarchy\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_hierarchy\Storage\QueryBuilderFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks if referenced entities are valid.
 */
class ValidHierarchyReferenceConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a ValidReferenceConstraintValidator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\entity_hierarchy\Storage\QueryBuilderFactory $queryBuilderFactory
   *   The query builder factory.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly QueryBuilderFactory $queryBuilderFactory
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_hierarchy.query_builder_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\Core\Field\FieldItemListInterface $value */
    /** @var ValidHierarchyReferenceConstraint $constraint */
    if (!isset($value)) {
      return;
    }

    // Collect new entities and IDs of existing entities across the field items.
    $new_entities = [];
    $target_ids = [];
    if (!$value->getEntity()) {
      // Can't validate if we don't have access to host entity.
      return;
    }
    foreach ($value as $delta => $item) {
      $target_id = $item->target_id;
      // We don't use a regular NotNull constraint for the target_id property as
      // NULL is allowed if the entity property contains an unsaved entity.
      // @see \Drupal\Core\TypedData\DataReferenceTargetDefinition::getConstraints()
      if (!$item->isEmpty() && $target_id === NULL) {
        if (!$item->entity->isNew()) {
          $this->context->buildViolation($constraint->nullMessage)
            ->atPath((string) $delta)
            ->addViolation();
          return;
        }
        $new_entities[$delta] = $item->entity;
      }

      // '0' or NULL are considered valid empty references.
      if (!empty($target_id)) {
        $target_ids[$delta] = $target_id;
      }
    }

    // Early opt-out if nothing to validate.
    if (!$new_entities && !$target_ids) {
      return;
    }

    $this_entity = $value->getEntity();
    $target_type = $this_entity->getEntityTypeId();
    $queryBuilder = $this->queryBuilderFactory->get($value->getFieldDefinition()->getFieldStorageDefinition()->getName(), $target_type);
    $descendant_records = $queryBuilder->findDescendants($this_entity);
    $descendant_record_ids = [];
    foreach ($descendant_records as $record) {
      $descendant_record_ids[] = $record->getId();
    }

    // Cannot reference self.
    $children = [$this_entity->id()];
    if (!empty($descendant_record_ids)) {
      $has_revisions = $this_entity->getEntityType()->hasKey('revision');
      $storage = $this->entityTypeManager->getStorage($this_entity->getEntityTypeId());
      $descendant_entities = $storage->getQuery()
        ->condition($this_entity->getEntityType()->getKey('id'), $descendant_record_ids, 'IN')
        ->accessCheck(FALSE)
        ->execute();
      $descendant_entities = array_flip($descendant_entities);
      foreach ($descendant_records as $record) {
        $node_id = $record->getId();
        if (!isset($descendant_entities[$node_id])) {
          continue;
        }
        if ($has_revisions && $record->getRevisionId() != $descendant_entities[$node_id]) {
          continue;
        }
        $children[] = $node_id;
      }
      $children = array_unique($children);
    }

    // Add violations on deltas with a target_id that is not valid.
    if ($target_ids && $children) {
      $deltas = array_flip($target_ids);
      foreach ($children as $entity_id) {
        if (isset($deltas[$entity_id])) {
          $this->context->buildViolation($constraint->message)
            ->setParameter('%type', $target_type)
            ->setParameter('%id', $entity_id)
            ->atPath((string) $deltas[$entity_id] . '.target_id')
            ->setInvalidValue($entity_id)
            ->addViolation();
        }
      }
    }
  }

}
