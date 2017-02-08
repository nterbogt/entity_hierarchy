<?php

namespace Drupal\entity_hierarchy\Plugin\Field\FieldType;


use Drupal\Core\Field\EntityReferenceFieldItemList;
use PNX\NestedSet\Node;

class EntityReferenceHierarchyFieldItemList extends EntityReferenceFieldItemList {

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    if (empty($this->list)) {
      // If this node was in the tree, it needs to be moved to a root node.
      $stubNode = $this->getNestedSetNodeFactory()->fromEntity($this->getEntity());
      $storage = $this->getTreeStorage();
      if (($existingNode = $storage->getNode($stubNode->getId(), $stubNode->getRevisionId())) && $existingNode->getDepth() > 0) {
        // @todo Use move to root method instead once it exists.
        $last_root = $this->getLastRootNode($storage);
        if ($children = $storage->findChildren($existingNode)) {
          $storage->moveSubTreeAfter($last_root, $existingNode);
        }
        else {
          $storage->deleteNode($existingNode);
          $storage->addRootNode($existingNode);
        }
      }
    }
    return parent::postSave($update);
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
   * Gets last root node in a tree.
   *
   * @param \PNX\NestedSet\NestedSetInterface $storage
   *   Node storage.
   *
   * @return \PNX\NestedSet\Node
   *   Last root node.
   */
  protected function getLastRootNode($storage) {
    $tree = $storage->getTree();
    $roots = array_filter($tree, function (Node $node) {
      return $node->getDepth() === 0;
    });
    $last_root = end($roots) ?: reset($tree);
    return $last_root;
  }

}
