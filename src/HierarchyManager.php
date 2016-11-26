<?php
// Todo: should limit parent reference such that it can't be a lower depth than the child.

/**
 * @file
 * Contains \Drupal\entity_hierarchy\HierarchyManager.
 */

namespace Drupal\entity_hierarchy;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * Defines a hierarchy manager.
 */
class HierarchyManager extends HierarchyBase implements HierarchyManagerInterface {
  use StringTranslationTrait;

  /**
   * Defines the maximum supported depth of a given hierarchy tree.
   */
  const HIERARCHY_MAX_DEPTH = 9;

  /**
   * Entity manager Service Object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * Config factory service object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Entity Field Manager object.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;


  /**
   * The entity query service object.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $entityQuery;


  /**
   * Hierarchies array for storing hierarchy objects.
   *
   * @var array
   */
  protected $hierarchies;

  /**
   * Hierarchy outline storage.
   *
   * @var \Drupal\entity_hierarchy\HierarchyOutlineStorageInterface
   */
  protected $hierarchyOutlineStorage;

  /**
   * Stores flattened hierarchy trees.
   *
   * @var array
   */
  protected $hierarchyTreeFlattened;

  /**
   * Constructs a HierarchyManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   Provides an interface for entity type managers.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   Interface for the translation.manager translation service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Defines the interface for a configuration object factory.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query factory.
   * @param \Drupal\entity_hierarchy\HierarchyOutlineStorageInterface $hierarchy_outline_storage
   *   The entity hierarchy storage object
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, TranslationInterface $translation, ConfigFactoryInterface $config_factory, QueryFactory $entity_query,
                              EntityFieldManagerInterface $field_manager, HierarchyOutlineStorageInterface $hierarchy_outline_storage) {
    $this->entityManager = $entity_manager;
    $this->stringTranslation = $translation;
    $this->configFactory = $config_factory;
    $this->entityQuery = $entity_query;
    $this->hierarchyOutlineStorage = $hierarchy_outline_storage;
    $this->entityFieldManager = $field_manager;
    $this->hierarchies = array();
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function hierarchyDefaultRecord($hid = NULL) {
    return new HierarchyBase($hid, TRUE, TRUE);
  }

  /**
   * {@inheritdoc}
   *
   * @see hierarchyGetAllowedParentTypes
   */
  public function hierarchyCanBeChild(NodeInterface $node) {
    $type = is_object($node) ? $node->getType() : $node;
//    return count($this->hierarchyGetAllowedParentTypes($type));
    return TRUE;
  }

  public function hierarchyGetNextChildWeight() {

  }

  public function hierarchyGetParentId($hid) {
    $node = Node::load($hid);
    $hierarchy_field = $this->hierarchyGetHierarchyField($node->getType(), $node->getEntityTypeId());
    $parent_id = $node->$hierarchy_field->target_id;
    return $parent_id;
  }

  /**
   * @inheritdoc
   */
  public function hierarchyLoadAllChildren($pid, $entity_type='node') {
    $node_types = $this->hierarchyGetAllNodeTypes();
    $children = array();
    foreach ($node_types as $type => $node) {
      // Determine the field being used for the hierarchy entity reference
      if ($field = $this->hierarchyGetHierarchyField($type, $entity_type)) {
        // Load all entities referenced to the given parent id ($pid)
        $query = $this->entityQuery->get($entity_type);
        $query->condition('type', $type);
        $query->condition($field, $pid);
        $entity_ids = $query->execute();
        if ($entity_ids){
          foreach ($entity_ids as $id) {
            $weight = $this->hierarchyGetWeight($id);
            $children[$weight] = $id;
          }
        }
      }
    }
    ksort($children);
    return $children;
  }

  public function hierarchyGetWeight($pid){
    $node = Node::load($pid);
    $hierarchy_field = $this->hierarchyGetHierarchyField($node->getType(), $node->getEntityTypeId());
    $weight = $node->$hierarchy_field->weight;
    return $weight;
  }

  /**
   * @inheritdoc
   */
  public function hierarchyGetHierarchyField($bundle, $entity_type='node') {
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    foreach ($fields as $field) {
      // We only expect zero or one field of this type
      // Todo: error checking for multiple fields
      if ($field->getType() == 'entity_reference_hierarchy') {
        $field_name = $field->getName();
        $type = $field_name;
        return $type;
      }
    }
    return NULL;
  }

  public function hierarchyUpdateWeight($hid, $weight) {
    $node = Node::load($hid);
    $hierarchy_field = $this->hierarchyGetHierarchyField($node->getType(), $node->getEntityTypeId());
    $node->$hierarchy_field->weight = $weight;
    $node->save();
    return $node;  // Don't really need this
  }

  // This function may be useful later when this module works on more than just nodes
  public function hierarchyGetAllNodeTypes() {
    $types = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple();
    return $types;
  }

  // This function may be useful later when this module works on more than just nodes
  public function hierarchyGetAllBundles() {
    $bundles = \Drupal::service("entity_type.bundle.info")->getAllBundleInfo();
    return $bundles;
  }

  /**
   * {@inheritdoc}
   *
   * @see hierarchyGetAllowedChildTypes
   */
  public function hierarchyCanBeParent($node) {
    $type = is_object($node) ? $node->getType() : $node;
    return count($this->hierarchyGetAllowedChildTypes($type));
  }

  /**
   * {@inheritdoc}
   */
  public function hierarchyGetAllowedChildTypes($bundle, $entity_type = 'node') {
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    $types = array();
    foreach ($fields as $field) {
      if ($field->getType() == 'entity_reference_hierarchy') {
        $settings = $field->getSettings();
        $bundles = $settings['handler_settings']['target_bundles'];
        foreach ($bundles as $bundle) {
          // Todo: use dependency injection instead of the following; see http://drupal.stackexchange.com/questions/187980/how-to-get-bundle-label-from-entity
          $bundle_label = \Drupal::entityTypeManager()
            ->getStorage($entity_type.'_type')
            ->load($bundle)
            ->label();
          $types[$bundle] = $bundle_label;
        }
      }
    }
    return $types;
  }



  /**
   * Return a list of valid possible hierarchy parents for the given child node
   * type.
   *
   * Todo: rewrite this method to use the depth and permissions only to determine list of parents.
   *
   * The list is built with the following steps:
   * 1) Get all allowed parent node types for all allowed node types using
   *    hierarchyGetAllowedParentTypes
   * 2) Get allowed parent node types for a given child node type using
   *    hierarchyGetAllowedParentTypes
   * 3) Build a tree of all possible parent nodes using hierarchyBuildTree and
   *    HierarchyOutlineStorage::hierarchyNodesByType
   * 4) Remove or disable parent titles that can't be parents using
   *    hierarchyTreeDisableTypes
   * 5) Remove items which the user does not have permission to access using
   *    hierarchyTreeDisableNoAccess
   * 6) Remove the option to set a child as its own parent using
   *    hierarchyTreeRemoveNid
   * 7) Convert the tree to a flattened list (with depth) using
   *    hierarchyFlattenTree
   *
   * @param string $child_type
   *   The node type of the child used to find valid potential parents
   * @param int/null $exclude
   *   Node ID of the parent that should be excluded from the list.
   * @return array
   *   The list of valid parents for a given node type.
   *
   * @see hierarchyGetParentSelector
   * @see hierarchyGetAllowedParentTypes
   * @see HierarchyOutlineStorage::hierarchyNodesByType
   * @see hierarchyBuildTree
   * @see hierarchyTreeDisableTypes
   * @see hierarchyTreeDisableNoAccess
   * @see hierarchyTreeRemoveNid
   * @see hierarchyFlattenTree
   */
  private function hierarchyParentOptions($child_type, $exclude = NULL) {
    return;
  }

  /**
   * Recursively convert the tree to a flattened list (with depth)
   *
   * @param $nodes
   *   The list of nodes to process
   * @param int $depth
   *   The depth of the list
   * @return array
   *   The flattened list to be used in the dropdown selector of available
   *   parent nodes.
   *
   * @see hierarchyParentOptions
   */
  private function hierarchyFlattenTree($nodes, $depth = 1) {
    $out = $children = array();
    foreach ($nodes as $nid => $node) {
      $node->depth = $depth;
      $children = array();
      if (!empty($node->children)) {
        $children = $this->hierarchyFlattenTree($node->children, $depth + 1);
      }
      // Only output this option if there are non-disabled children.
      if (!$node->disabled || $children) {
        $out[$nid] = $node;
        $out += $children;
      }
    }
    return $out;
  }

  /**
   * Prepare an individual item to be added/removed to the database.
   *
   * If the item already has a hierarchy id (hid) do the following:
   * 1) If the item doesn't have a parent node id (pnid), it is deleted via
   *    hierarchyDeleteRecord. Otherwise, load the current item from the
   *    database using hierarchyGetRecord
   * 2) Adjust the child weight (cweight) via hierarchyGetParentNextChildWeight.
   * 3) Update the database with the new weight using updateHierarchy.
   *
   * If the item doesn't already have an hid:
   * 1) Load the next available cweight using hierarchyGetParentNextChildWeight.
   * 2) Insert a new hierarchy record into the database via insertHierarchy.
   *
   * @param object $item
   *   The hierarchy object to be processed before adding the item to the
   *   database, updating an existing item, or deleting the item from the
   *   database.
   *
   * @see HierarchyManagerInterface::hierarchySaveNode
   * @see hierarchyDeleteRecord
   * @see hierarchyGetRecord
   * @see hierarchyGetNextChildWeight
   * @see insertHierarchy
   * @see updateHierarchy
   */
  private function hierarchyRecordSave(&$item) {
    if (!empty($item->hid)) {
        $existing_item = $this->hierarchyGetRecord($item->hid);
        // If the parent has been changed:
        if ($existing_item->pnid !== $item->pnid) {
          $item->cweight = $this->getNexChildWeight();
        }
    }
    else {
      $item->cweight = $this->getNexChildWeight();
    }
    if ($item->pnid) {
      if (empty($item->hid)) {
        $this->insertHierarchy($item);
      }
      else {
        $this->updateHierarchy($item);
      }
    }
  }

  /**
   * Add a new hierarchy record to the database using the
   * HierarchyOutlineStorage class.
   *
   * @param object $item
   *   The hierarchy object to be written to the database.
   * @return mixed
   *   Todo: figure out what's being returned
   *
   * @see HierarchyOutlineStorage::insert
   * @see hierarchyRecordSave
   */
  private function insertHierarchy($item){
    return $this->hierarchyOutlineStorage->insert($item);
  }

  /**
   * {@inheritdoc}
   */
  public function updateHierarchy($item){
    unset($item->remove);
    // Here we typecast the $item object to an array, and PHP is smart enough to convert it.
    return $this->hierarchyOutlineStorage->update($item->hid, (array)$item);
  }

  /**
   * Loads a single hierarchy item from the database using the
   * HierarchyOutlineStorage class.
   *
   * @param int $hid
   *   The hierarchy id to be loaded from the database.
   * @return mixed
   *   Todo: figure out what's being returned
   *
   * @see HierarchyOutlineStorage::hierarchyRecordLoad
   * @see hierarchyRecordSave
   */
  private function hierarchyGetRecord($hid){
    return $this->hierarchyOutlineStorage->hierarchyRecordLoad($hid);
  }

  /**
   * Add a new hierarchy object.
   *
   * @param int $hid
   *   The hierarchy ID is the main object ID.
   */
  public function hierarchyAddHierarchyObject($hid) {
    $this->hierarchies[$hid] = new HierarchyBase($hid, TRUE, TRUE);
  }

}
