<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\HierarchyManagerInterface.
 */

namespace Drupal\nodehierarchy;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;


/**
 * Provides an interface defining a hierarchy manager.
 */
interface HierarchyManagerInterface {

  /**
   * Builds the common elements of the hierarchy form for the node and outline forms.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\node\NodeInterface $node
   *   The node whose form is being viewed.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account viewing the form.
   * @param bool $collapsed
   *   If TRUE, the fieldset starts out collapsed.
   *
   * @return array
   *   The form structure, with the hierarchy elements added.
   */
  public function addFormElements(array $form, FormStateInterface $form_state, NodeInterface $node, AccountInterface $account, $collapsed = TRUE);

}
