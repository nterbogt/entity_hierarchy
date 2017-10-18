<?php

namespace Drupal\entity_hierarchy\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;

/**
 * Argument to limit to parent of an entity.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("entity_hierarchy_argument_is_sibling_of_entity")
 */
class HierarchyIsSiblingOfEntity extends EntityHierarchyArgumentPluginBase {

  /**
   * Set up the query for this argument.
   *
   * The argument sent may be found at $this->argument.
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    // Load the actual entity.
    $filtered = FALSE;
    if ($entity = $this->loadEntity()) {
      $stub = $this->nodeKeyFactory->fromEntity($entity);
      if ($node = $this->getTreeStorage()->findParent($stub)) {
        // Query between a range with fixed depth, excluding the original node.
        $filtered = TRUE;
        $expression = "$this->tableAlias.$this->realField BETWEEN :lower and :upper AND $this->tableAlias.$this->realField <> :lower AND $this->tableAlias.depth = :depth AND $this->tableAlias.id != :self";
        $arguments = [
          ':lower' => $node->getLeft(),
          ':upper' => $node->getRight(),
          ':depth' => $node->getDepth() + 1,
          ':self' => $stub->getId(),
        ];

        $this->query->addWhereExpression(0, $expression, $arguments);
      }
    }
    // The parent entity doesn't exist, or isn't in the tree and hence has no
    // children.
    if (!$filtered) {
      // Add a killswitch.
      $this->query->addWhereExpression(0, '1 <> 1');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    unset($form['depth']);
  }

}
