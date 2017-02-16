<?php

namespace Drupal\Tests\entity_hierarchy\Kernel;

use Drupal\Component\Utility\Unicode;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\entity_hierarchy\EntityHierarchyTestTrait;

/**
 * Defines a base class for entity hierarchy tests.
 */
abstract class EntityHierarchyKernelTestBase extends KernelTestBase {

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
    $this->installEntitySchema(static::ENTITY_TYPE);
    $this->setupEntityHierarchyField(static::ENTITY_TYPE, static::ENTITY_TYPE, static::FIELD_NAME);
    $this->additionalSetup();
  }

}
