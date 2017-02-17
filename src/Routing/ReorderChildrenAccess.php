<?php

namespace Drupal\entity_hierarchy\Routing;

use Drupal\Core\Access\AccessCheckInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Defines a class for limiting the children form to entities with hierarchies.
 */
class ReorderChildrenAccess implements AccessCheckInterface {

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new ReorderChildrenAccess object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity field manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Route match.
   */
  public function __construct(EntityFieldManagerInterface $entityFieldManager, RouteMatchInterface $routeMatch) {
    $this->entityFieldManager = $entityFieldManager;
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Route $route) {
    return $route->hasRequirement(EntityHierarchyRouteProvider::ENTITY_HIERARCHY_HAS_FIELD);
  }

  /**
   * Checks access.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   Route being access checked.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, Request $request = NULL, AccountInterface $account = NULL) {
    $entity_type = $route->getOption(EntityHierarchyRouteProvider::ENTITY_HIERARCHY_ENTITY_TYPE);
    $entity = $this->routeMatch->getParameter($entity_type);
    $fields = $this->entityFieldManager->getFieldMapByFieldType('entity_reference_hierarchy');
    $target_bundles = [];
    if (isset($fields[$entity_type])) {
      // See if any bundles point to this entity.
      // We only consider this entity type, there is no point in a hierarchy
      // that spans entity-types as you cannot have more than a single level.
      foreach ($fields[$entity_type] as $field_name => $detail) {
        foreach ($detail['bundles'] as $bundle) {
          /** @var \Drupal\Core\Field\FieldDefinitionInterface $field */
          $field = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle)[$field_name];
          $settings = $field->getSetting('handler_settings');
          if (!isset($settings['target_bundles'])) {
            // No target bundles means any can be referenced, return early.
            return AccessResult::allowed()->setCacheMaxAge(0);
          }
          $target_bundles = array_unique(array_merge($target_bundles, $settings['target_bundles']));
        }
      }
    }
    if (in_array($entity->bundle(), $target_bundles, TRUE)) {
      return AccessResult::allowed()->setCacheMaxAge(0);
    }
    return AccessResult::forbidden();
  }

}
