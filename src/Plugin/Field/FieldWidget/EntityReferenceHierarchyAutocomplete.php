<?php

namespace Drupal\entity_hierarchy\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;

/**
 * Widget that uses autocomplete.
 *
 * @FieldWidget(
 *   id = "entity_reference_hierarchy_autocomplete",
 *   label = @Translation("Autocomplete"),
 *   description = @Translation("An autocomplete text field with associated data."),
 *   field_types = {
 *     "entity_reference_hierarchy"
 *   }
 * )
 */
class EntityReferenceHierarchyAutocomplete extends EntityReferenceAutocompleteWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $widget = array(
      '#attributes' => ['class' => ['form--inline', 'clearfix']],
      '#theme_wrappers' => ['container'],
    );
    $widget['target_id'] = parent::formElement($items, $delta, $element, $form, $form_state);
    $widget['weight'] = array(
      '#type' => 'number',
      '#size' => '4',
      '#default_value' => isset($items[$delta]) ? $items[$delta]->weight : 1,
      '#weight' => 10,
    );

    if ($this->fieldDefinition->getFieldStorageDefinition()->isMultiple()) {
      $widget['weight']['#placeholder'] = $this->fieldDefinition->getSetting('weight_label');
    }
    else {
      $widget['weight']['#title'] = $this->fieldDefinition->getSetting('weight_label');
    }

    return $widget;
  }

}
