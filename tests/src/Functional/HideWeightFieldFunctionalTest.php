<?php

namespace Drupal\Tests\entity_hierarchy\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\entity_hierarchy\Plugin\Field\FieldWidget\EntityReferenceHierarchyAutocomplete;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\entity_hierarchy\EntityHierarchyTestTrait;

/**
 * Defines a class for testing the ability to hide the weight field.
 *
 * @group entity_hierarchy
 */
class HideWeightFieldFunctionalTest extends BrowserTestBase {

  use EntityHierarchyTestTrait;

  const FIELD_NAME = 'parents';
  const ENTITY_TYPE = 'entity_test';
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_hierarchy',
    'entity_test',
    'system',
    'user',
    'dbal',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->setupEntityHierarchyField(static::ENTITY_TYPE, static::ENTITY_TYPE, static::FIELD_NAME);
    $this->additionalSetup();
    $this->getEntityFormDisplay(self::ENTITY_TYPE, self::ENTITY_TYPE, 'default')
      ->setComponent(self::FIELD_NAME, [
        'type' => 'entity_reference_hierarchy_autocomplete',
        'weight' => 20,
      ])
      ->save();
  }

  /**
   * Gets entity form display.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle.
   * @param string $form_mode
   *   Form mode.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Form display.
   */
  protected function getEntityFormDisplay($entity_type, $bundle, $form_mode) {
    $entity_form_display = EntityFormDisplay::load($entity_type . '.' . $bundle . '.' . $form_mode);

    // If not found, create a fresh entity object. We do not preemptively create
    // new entity form display configuration entries for each existing entity
    // type and bundle whenever a new form mode becomes available. Instead,
    // configuration entries are only created when an entity form display is
    // explicitly configured and saved.
    if (!$entity_form_display) {
      $entity_form_display = EntityFormDisplay::create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => $form_mode,
        'status' => TRUE,
      ]);
    }

    return $entity_form_display;
  }

  /**
   * Tests ordered storage in nested set tables.
   */
  public function testReordering() {
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
    $this->drupalGet('/entity_test/add');
    $assert = $this->assertSession();
    $assert->fieldExists('parents[0][weight]');
    // Change the field to hide the weight field.
    $field = FieldConfig::load("entity_test.entity_test.parents");
    $this->getEntityFormDisplay(self::ENTITY_TYPE, self::ENTITY_TYPE, 'default')
      ->setComponent(self::FIELD_NAME, [
        'type' => 'entity_reference_hierarchy_autocomplete',
        'weight' => 20,
        'settings' => ['hide_weight' => TRUE] + EntityReferenceHierarchyAutocomplete::defaultSettings(),
      ])
      ->save();
    $field->setSetting('hide_weight', TRUE);
    $field->save();
    $this->drupalGet('/entity_test/add');
    $assert->fieldNotExists('parents[0][weight]');
    // Submit the form.
    $name = $this->randomMachineName();
    $this->submitForm([
      'parents[0][target_id][target_id]' => sprintf('%s (%s)', $this->parent->label(), $this->parent->id()),
      'name[0][value]' => $name,
    ], 'Save');
    $saved = $this->container->get('entity_type.manager')->getStorage('entity_test')->loadByProperties(['name' => $name]);
    $this->assertCount(1, $saved);
  }

}
