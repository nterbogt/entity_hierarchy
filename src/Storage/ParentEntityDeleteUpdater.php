<?php

declare(strict_types=1);

namespace Drupal\entity_hierarchy\Storage;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_hierarchy\Information\ParentCandidateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for updating the tree when a parent is deleted.
 */
class ParentEntityDeleteUpdater implements ContainerInjectionInterface {

  /**
   * Constructs a new ParentEntityRevisionUpdater object.
   *
   * @param \Drupal\entity_hierarchy\Information\ParentCandidateInterface $parentCandidate
   *   Parent candidate service.
   * @param \Drupal\entity_hierarchy\Storage\QueryBuilderFactory $queryBuilderFactory
   *   Query builder factory.
   */
  public function __construct(
    protected readonly ParentCandidateInterface $parentCandidate,
    protected readonly QueryBuilderFactory $queryBuilderFactory
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_hierarchy.information.parent_candidate'),
      $container->get('entity_hierarchy.query_builder_factory')
    );
  }

  /**
   * Moves children to their grandparent or root.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Parent being deleted.
   */
  public function moveChildren(ContentEntityInterface $entity) {
    if (!$entity->isDefaultRevision()) {
      // We don't do anything here.
      return;
    }
    if (!$fields = $this->parentCandidate->getCandidateFields($entity)) {
      // There are no fields that could point to this entity.
      return;
    }
    foreach ($fields as $field_name) {
      $queryBuilder = $this->queryBuilderFactory->get($field_name, $entity->getEntityTypeId());
      if ($records = $queryBuilder->findChildren($entity)) {
        $parent = $queryBuilder->findParent($entity);
        foreach ($records as $record) {
          $child_node = $record->getEntity();
          $child_node->{$field_name}->target_id = ($parent ? $parent->id() : NULL);
          if ($child_node->getEntityType()->hasKey('revision')) {
            // We don't want a new revision here.
            $child_node->setNewRevision(FALSE);
          }
          $child_node->save();
        }
      }
    }
  }

}
