<?php

namespace Drupal\entity_hierarchy\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_hierarchy\Storage\InsertPosition;
use Drupal\entity_hierarchy\Storage\NestedSetStorage;
use PNX\NestedSet\Node;
use PNX\NestedSet\NodeKey;

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
 *   list_class = "\Drupal\entity_hierarchy\Plugin\Field\FieldType\EntityReferenceHierarchyFieldItemList"
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
  public function delete() {
    parent::delete();
    $storage = $this->getTreeStorage();
    $nodeFactory = $this->getNodeKeyFactory();
    $stubNode = $nodeFactory->fromEntity($this->getEntity());
    if ($existingNode = $storage->getNode($stubNode)) {
      // The NestedSet implementation handles moving children up a layer.
      $storage->deleteNode($existingNode);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    // Get the key factory and tree storage services.
    $nodeKeyFactory = $this->getNodeKeyFactory();
    $storage = $this->getTreeStorage();

    // Get the field name.
    $fieldName = $this->getFieldDefinition()->getName();

    // Get the parent/child entities and their node-keys in the nested set.
    $parentEntity = $this->get('entity')->getValue();
    $parentKey = $nodeKeyFactory->fromEntity($parentEntity);
    $childEntity = $this->getEntity();
    $childKey = $nodeKeyFactory->fromEntity($childEntity);

    // Determine if this is a new node in the tree.
    $isNewNode = FALSE;
    if (!$childNode = $storage->getNode($childKey)) {
      $isNewNode = TRUE;
      // As we're going to be adding instead of moving, a key is all we require.
      $childNode = $childKey;
    }

    // Does the parent already exist in the tree.
    if ($existingParent = $storage->getNode($parentKey)) {
      // If there are no siblings, we simply insert/move below.
      $insertPosition = new InsertPosition($existingParent, $isNewNode, InsertPosition::DIRECTION_BELOW);

      // But if there are siblings, we need to ascertain the correct position in
      // the order.
      if ($siblingEntities = $this->getSiblingEntities($storage, $existingParent, $childNode)) {
        // Group the siblings by their weight.
        $weightOrderedSiblings = $this->groupSiblingsByWeight($siblingEntities, $fieldName);
        $weight = $this->get('weight')->getValue();
        $insertPosition = $this->getInsertPosition($weightOrderedSiblings, $weight, $isNewNode);
      }
      $insertPosition->performInsert($storage, $childNode);
      return;
    }
    // We need to create a node for the parent in the tree.
    $parentNode = $storage->addRootNode($parentKey);
    (new InsertPosition($parentNode, $isNewNode, InsertPosition::DIRECTION_BELOW))->performInsert($storage, $childNode);
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
   * @return \Drupal\entity_hierarchy\Storage\NestedSetStorage
   *   Tree storage.
   */
  protected function getTreeStorage() {
    $fieldDefinition = $this->getFieldDefinition();
    return \Drupal::service('entity_hierarchy.nested_set_storage_factory')->get($fieldDefinition->getName(), $fieldDefinition->getTargetEntityTypeId());
  }

  /**
   * Returns the node factory.
   *
   * @return \Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory
   *   The factory.
   */
  protected function getNodeKeyFactory() {
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

  /**
   * Gets siblings.
   *
   * @param \Drupal\entity_hierarchy\Storage\NestedSetStorage $storage
   *   Storage.
   * @param \PNX\NestedSet\Node $parentNode
   *   Existing parent node.
   * @param \PNX\NestedSet\Node|\PNX\NestedSet\NodeKey $childNode
   *   Child node.
   *
   * @return \SplObjectStorage|bool
   *   Map of entities keyed by node or FALSE if no siblings.
   */
  protected function getSiblingEntities(NestedSetStorage $storage, Node $parentNode, $childNode) {
    if ($siblingNodes = array_filter($storage->findChildren($parentNode->getNodeKey()), function (Node $node) use ($childNode) {
      if ($childNode instanceof NodeKey) {
        // Exclude self and all revisions.
        return $childNode->getId() !== $node->getNodeKey()->getId();
      }
      // Exclude self and all revisions.
      return $childNode->getNodeKey()->getId() !== $node->getNodeKey()->getId();
    })) {
      return $this->loadSiblingEntities($siblingNodes);
    }
    return FALSE;
  }

  /**
   * Group siblings by weight.
   *
   * @param \SplObjectStorage $siblingEntities
   *   Sibling entities keyed by nested set nodes.
   * @param string $fieldName
   *   Field name to detect weight from.
   *
   * @return array
   *   Array of nested set nodes grouped by weight.
   */
  public function groupSiblingsByWeight($siblingEntities, $fieldName) {
    $weightMap = [];
    foreach ($siblingEntities as $node) {
      $siblingEntity = $siblingEntities->offsetGet($node);
      $weightMap[$siblingEntity->{$fieldName}->weight][] = $node;
    }
    ksort($weightMap);
    return $weightMap;
  }

  /**
   * Gets the insert position for the new child.
   *
   * @param array $weightOrderedSiblings
   *   Sibling nodes, grouped by weight.
   * @param int $weight
   *   Desired weight amongst siblings of the new child.
   * @param bool $isNewNode
   *   TRUE if the node is brand new, FALSE if it needs to be moved from
   *   elsewhere in the tree.
   *
   * @return \Drupal\entity_hierarchy\Storage\InsertPosition
   *   Insert position.
   */
  public function getInsertPosition(array $weightOrderedSiblings, $weight, $isNewNode) {
    if (isset($weightOrderedSiblings[$weight])) {
      // There are already nodes at the same weight, insert it with them.
      return new InsertPosition(end($weightOrderedSiblings[$weight]), $isNewNode);
    }

    // There are no nodes at this weight, we need to find the right position.
    $firstGroup = reset($weightOrderedSiblings);
    $start = key($weightOrderedSiblings);
    if ($weight < $start) {
      // We're going to position before all existing nodes.
      return new InsertPosition(reset($firstGroup), $isNewNode);
    }
    foreach (array_keys($weightOrderedSiblings) as $weightPosition) {
      if ($weight < $weightPosition) {
        return new InsertPosition(reset($weightOrderedSiblings[$weightPosition]), $isNewNode);
      }
    }
    // We're inserting at the end.
    $lastGroup = end($weightOrderedSiblings);
    return new InsertPosition(end($lastGroup), $isNewNode, InsertPosition::DIRECTION_AFTER);
  }

}
