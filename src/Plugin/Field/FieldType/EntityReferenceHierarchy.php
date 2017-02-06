<?php

namespace Drupal\entity_hierarchy\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use PNX\NestedSet\Node;

/**
 * Plugin implementation of the 'entity_reference_hierarchy' field type.
 *
 * @FieldType(
 *   id = "entity_reference_hierarchy",
 *   label = @Translation("Entity reference hierarchy"),
 *   description = @Translation("Entity parent reference with weight."),
 *   category = @Translation("Reference"),
 *   default_widget = "entity_reference_hierarchy_autocomplete",
 *   default_formatter = "entity_reference_hierarchy_label",
 *   list_class = "\Drupal\Core\Field\EntityReferenceFieldItemList"
 * )
 */
class EntityReferenceHierarchy extends EntityReferenceItem {

  /**
   * Defines the minimum weight of a child (but has the highest priority).
   */
  const HIERARCHY_MIN_CHILD_WEIGHT = -50;

  /**
   * Defines the maximum weight of a child (but has the lowest priority).
   */
  const HIERARCHY_MAX_CHILD_WEIGHT = 50;

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $weight_definition = DataDefinition::create('integer')
      ->setLabel($field_definition->getSetting('weight_label'));
    $weight_definition->addConstraint('Range', ['min' => self::HIERARCHY_MIN_CHILD_WEIGHT]);
    $weight_definition->addConstraint('Range', ['max' => self::HIERARCHY_MAX_CHILD_WEIGHT]);
    $properties['weight'] = $weight_definition;
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);
    $schema['columns']['weight'] = array(
      'type' => 'int',
      'unsigned' => FALSE,
    );
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return array(
      'weight_label' => t('Weight'),
      'weight_min' => self::HIERARCHY_MIN_CHILD_WEIGHT,
      'weight_max' => self::HIERARCHY_MAX_CHILD_WEIGHT,
    ) + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::fieldSettingsForm($form, $form_state);

    $elements['weight_min'] = [
      '#type' => 'number',
      '#title' => t('Minimum'),
      '#default_value' => $this->getSetting('weight_min'),
    ];
    $elements['weight_max'] = [
      '#type' => 'number',
      '#title' => t('Maximum'),
      '#default_value' => $this->getSetting('weight_max'),
    ];
    $elements['weight_label'] = [
      '#type' => 'textfield',
      '#title' => t('Weight Label'),
      '#default_value' => $this->getSetting('weight_label'),
      '#description' => t('The weight of this child with respect to other children.')
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function getPreconfiguredOptions() {
    // In the base EntityReference class, this is used to populate the
    // list of field-types with options for each destination entity type.
    // Too much work, we'll just make people fill that out later.
    // Also, keeps the field type dropdown from getting too cluttered.
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    $entity = $this->get('entity')->getValue();
    $nodeFactory = $this->getNestedSetNodeFactory();
    $parentNode = $nodeFactory->fromEntity($entity);
    $child = $this->getEntity();
    $childNode = $nodeFactory->fromEntity($child);
    $storage = $this->getTreeStorage();

    if ($existingNode = $storage->getNode($parentNode->getId(), $parentNode->getRevisionId())) {
      if ($siblings = $storage->findChildren($existingNode)) {
        // We need to conserve order here.
        $siblingEntities = $this->loadSiblingEntities($siblings);
        $fieldDefinition = $this->getFieldDefinition();
        $fieldName = $fieldDefinition->getName();
        // @todo refactor this onto Drupal tree storage wrapper?
        $weightMap = [];
        foreach ($siblingEntities as $node) {
          $siblingEntity = $siblingEntities->offsetGet($node);
          $weightMap[$siblingEntity->{$fieldName}->weight][] = $node;
        }
        $weight = $this->get('weight')->getValue();
        if (isset($weightMap[$weight])) {
          // There are already nodes at the same weight.
          $position = end($weightMap[$weight]);
          $method = 'addNodeBefore';
        }
        else {
          // There are no nodes at this weight, we need to make space.
          ksort($weightMap);
          $firstGroup = reset($weightMap);
          $position = reset($firstGroup);
          $method = 'addNodeAfter';
          $start = key($weightMap);
          if ($weight < $start) {
            // We're going to position before all existing nodes.
            $method = 'addNodeBefore';
          }
          else {
            foreach (array_keys($weightMap) as $weightPosition) {
              if ($weight < $weightPosition) {
                $method = 'addNodeBefore';
                $position = reset($weightMap[$weightPosition]);
              }
            }
            if ($method === 'addNodeAfter') {
              // We're inserting at the end.
              $lastGroup = end($weightMap);
              $position = end($lastGroup);
            }
          }
        }
        call_user_func_array([$storage, $method], [$position, $childNode]);
      }
      else {
        // No particular order needed here.
        $storage->addNodeBelow($existingNode, $childNode);
      }
    }
    else {
      $parentNode = $storage->addRootNode($parentNode);
      $storage->addNodeBelow($parentNode, $childNode);
    }
  }

  /**
   * Returns the storage handler for the given entity-type.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   Storage handler.
   */
  protected function entityTypeStorage($entity_type_id) {
    return \Drupal::entityTypeManager()->getStorage($entity_type_id);
  }

  /**
   * Returns the tree storage.
   *
   * @return \PNX\NestedSet\NestedSetInterface
   *   Tree storage.
   */
  protected function getTreeStorage() {
    $fieldDefinition = $this->getFieldDefinition();
    return \Drupal::service('entity_hierarchy.nested_set_storage_factory')->get($fieldDefinition->getName(), $fieldDefinition->getTargetEntityTypeId());
  }

  /**
   * Returns the node factory.
   *
   * @return \Drupal\entity_hierarchy\Storage\NestedSetNodeFactory
   *   The factory.
   */
  protected function getNestedSetNodeFactory() {
    return \Drupal::service('entity_hierarchy.nested_set_node_factory');
  }

  /**
   * Gets the entity type definition.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   Entity type.
   */
  protected function entityTypeDefinition() {
    return \Drupal::entityTypeManager()->getDefinition($this->getFieldDefinition()->getTargetEntityTypeId());
  }

  /**
   * Loads other children of the given parent.
   *
   * @param \PNX\NestedSet\Node[] $siblings
   *   Target siblings.
   *
   * @return \SplObjectStorage
   *   Map of entities keyed by node.
   *
   * @todo move this to its own service or onto the tree storage wrapper?
   */
  protected function loadSiblingEntities(array $siblings) {
    $fieldDefinition = $this->getFieldDefinition();
    $entityType = $this->entityTypeDefinition();
    $entityTypeId = $fieldDefinition->getTargetEntityTypeId();
    $entityStorage = $this->entityTypeStorage($entityTypeId);
    $siblingEntities = new \SplObjectStorage();
    foreach ($siblings as $node) {
      if ($entityType->hasKey('revision')) {
        $siblingEntities[$node] = $entityStorage->loadRevision($node->getRevisionId());
      }
      else {
        $siblingEntities[$node] = $entityStorage->load($node->getId());
      }
    }

    return $siblingEntities;
  }

}
