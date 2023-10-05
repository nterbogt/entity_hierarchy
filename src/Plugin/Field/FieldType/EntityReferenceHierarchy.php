<?php

namespace Drupal\entity_hierarchy\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;

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
 *   cardinality = 1,
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
    $schema['columns']['weight'] = [
      'type' => 'int',
      'unsigned' => FALSE,
      'size' => 'small',
      'not null' => TRUE,
    ];
    // Add weight index.
    $schema['indexes']['weight'] = ['weight'];
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'weight_label' => t('Weight'),
      'weight_min' => self::HIERARCHY_MIN_CHILD_WEIGHT,
      'weight_max' => self::HIERARCHY_MAX_CHILD_WEIGHT,
    ] + parent::defaultFieldSettings();
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
      '#description' => t('The weight of this child with respect to other children.'),
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
    return [];
  }

}
