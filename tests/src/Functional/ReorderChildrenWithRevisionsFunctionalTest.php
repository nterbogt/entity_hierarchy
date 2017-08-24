<?php

namespace Drupal\Tests\entity_hierarchy\Functional;
use Drupal\entity_test\Entity\EntityTestRev;

/**
 * Tests reordering with revisions.
 *
 * @group entity_hierarchy.
 */
class ReorderChildrenWithRevisionsFunctionalTest extends ReorderChildrenFunctionalTest {

  const ENTITY_TYPE = 'entity_test_rev';

  /**
   * {@inheritdoc}
   */
  protected function createTestEntity($parentId, $label = 'Child 1', $weight = 0) {
    $values = [
      'type' => static::ENTITY_TYPE,
      $this->container->get('entity_type.manager')->getDefinition(static::ENTITY_TYPE)->getKey('label') => $label,
    ];
    if ($parentId) {
      $values[static::FIELD_NAME] = [
        'target_id' => $parentId,
        'weight' => $weight,
      ];
    }
    $entity = $this->doCreateTestEntity($values);
    // Create a revision with the wrong weight.
    $entity->setNewRevision(TRUE);
    if ($parentId) {
      $entity->{static::FIELD_NAME}->weight = -1 * $weight;
    }
    $entity->save();
    // And a default revision with the correct weight.
    $entity->setNewRevision(TRUE);
    if ($parentId) {
      $entity->{static::FIELD_NAME}->weight = $weight;
    }
    $entity->save();
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function doCreateTestEntity(array $values) {
    $entity = EntityTestRev::create($values);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function testAddChildLinks() {
    $this->assertTrue(TRUE, "We don't need to run this one for revisions.");
  }

}
