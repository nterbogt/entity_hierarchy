<?php

namespace Drupal\Tests\entity_hierarchy\Kernel;

use Drupal\Component\Utility\Unicode;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Defines a base class for entity hierarchy tests.
 */
abstract class EntityHierarchyKernelTestBase extends KernelTestBase {

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
   * Test parent.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $parent;
  /**
   * Node key for parent.
   *
   * @var \PNX\NestedSet\NodeKey
   */
  protected $parentStub;
  /**
   * Tree storage.
   *
   * @var \PNX\NestedSet\Storage\DbalNestedSet
   */
  protected $treeStorage;
  /**
   * Node key factory.
   *
   * @var \Drupal\entity_hierarchy\Storage\NestedSetNodeKeyFactory
   */
  protected $nodeFactory;

  /**
   * Perform additional setup.
   */
  protected function additionalSetup() {
    $this->treeStorage = $this->container->get('entity_hierarchy.nested_set_storage_factory')
      ->get(static::FIELD_NAME, static::ENTITY_TYPE);

    $this->parent = $this->createTestEntity(NULL, 'Parent');
    $this->nodeFactory = $this->container->get('entity_hierarchy.nested_set_node_factory');
    $this->parentStub = $this->nodeFactory->fromEntity($this->parent);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema(static::ENTITY_TYPE);
    $this->setupEntityHierarchyField(static::ENTITY_TYPE, static::ENTITY_TYPE, static::FIELD_NAME);
    $this->additionalSetup();
  }

  /**
   * Creates a new entity hierarchy field for the given bundle.
   *
   * @param string $entity_type_id
   *   Entity type to add the field to.
   * @param string $bundle
   *   Bundle of field.
   * @param string $field_name
   *   Field name.
   */
  protected function setupEntityHierarchyField($entity_type_id, $bundle, $field_name) {
    $storage = FieldStorageConfig::create([
      'entity_type' => $entity_type_id,
      'field_name' => $field_name,
      'id' => "$entity_type_id.$field_name",
      'type' => 'entity_reference_hierarchy',
      'settings' => [
        'target_type' => $entity_type_id,
      ],
    ]);
    $storage->save();
    $config = FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type_id,
      'bundle' => $bundle,
      'id' => "$entity_type_id.$bundle.$field_name",
      'label' => Unicode::ucfirst($field_name),
    ]);
    $config->save();
  }

  /**
   * Create child entities.
   *
   * @param int $parentId
   *   Parent ID.
   * @param int $count
   *   (optional) Number to create. Defaults to 5
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Child entities
   */
  protected function createChildEntities($parentId, $count = 5) {
    $entities = [];
    foreach (range(1, $count) as $i) {
      $label = sprintf('Child %d', $i);
      $entities[$label] = $this->doCreateChildTestEntity($parentId, $label, -1 * $i);
    }
    return $entities;
  }

  /**
   * Creates a new test entity.
   *
   * @param int|null $parentId
   *   Parent ID
   * @param string $label
   *   Entity label
   * @param int $weight
   *   Entity weight amongst sibling, if parent is set.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   New entity.
   */
  protected function createTestEntity($parentId, $label = 'Child 1', $weight = 0) {
    $values = [
      'type' => static::ENTITY_TYPE,
      'name' => $label,
    ];
    if ($parentId) {
      $values[static::FIELD_NAME] = [
        'target_id' => $parentId,
        'weight' => $weight,
      ];
    }
    $entity = $this->doCreateTestEntity($values);
    $entity->save();
    return $entity;
  }

  /**
   * Creates the test entity.
   *
   * @param array $values
   *   Entity values.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Created entity.
   */
  protected function doCreateTestEntity($values) {
    $entity = EntityTest::create($values);
    return $entity;
  }

  /**
   * Creates a new test entity.
   *
   * @param int|null $parentId
   *   Parent ID
   * @param string $label
   *   Entity label
   * @param int $weight
   *   Entity weight amongst sibling, if parent is set.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   New entity.
   */
  protected function doCreateChildTestEntity($parentId, $label, $weight) {
    return $this->createTestEntity($parentId, $label, $weight);
  }

}
