<?php

namespace Drupal\entity_hierarchy_workbench_access\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ExecutionContextInterface;

/**
 * Supports validating allowed parent.
 *
 * @Constraint(
 *   id = "ValidHierarchySection",
 *   label = @Translation("Valid hierarchy selection", context = "Validation"),
 *   type = "entity:comment"
 * )
 */
class ValidEntityHierarchySection extends Constraint implements ConstraintValidatorInterface {

  /**
   * Violation message. Use the same message as FormValidator.
   *
   * Note that the name argument is not sanitized so that translators only have
   * one string to translate. The name is sanitized in self::validate().
   *
   * @var string
   */
  public $message = 'You are not allowed to create content in this section.';

  /**
   * @var \Symfony\Component\Validator\ExecutionContextInterface
   */
  protected $context;

  /**
   * {@inheritdoc}
   */
  public function initialize(ExecutionContextInterface $context) {
    $this->context = $context;
  }

  /**
   * {@inheritdoc}
   */
  public function validatedBy() {
    return get_class($this);
  }

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    /** @var \Drupal\Core\Field\FieldItemListInterface $items */
    $user = \Drupal::currentUser();
    if ($user->hasPermission('bypass workbench access')) {
      return;
    }
    /** @var \Drupal\workbench_access\WorkbenchAccessManagerInterface $workbench_access_manager */
    $workbench_access_manager = \Drupal::service('plugin.manager.workbench_access.scheme');
    $parent = $items->entity;
    $result = $workbench_access_manager->getActiveScheme()->checkEntityAccess($parent, 'update', $user, $workbench_access_manager);
    if ($result->isForbidden()) {
      $this->context->addViolation($this->message);
    }
  }

}
