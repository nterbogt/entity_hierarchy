<?php

namespace Drupal\entity_hierarchy_breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_hierarchy\Storage\QueryBuilderFactory;
use Drupal\entity_hierarchy\Storage\Record;
use Symfony\Component\Routing\Route;

/**
 * Entity hierarchy based breadcrumb builder.
 */
class HierarchyBasedBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * HierarchyBasedBreadcrumbBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   * @param \Drupal\Core\Routing\AdminContext $adminContext
   *   The admin context service.
   * @param \Drupal\entity_hierarchy\Storage\QueryBuilderFactory $queryBuilderFactory
   *   Query builder factory.
   */
  public function __construct(
    protected EntityFieldManagerInterface $entityFieldManager,
    protected AdminContext $adminContext,
    protected QueryBuilderFactory $queryBuilderFactory
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    if ($this->adminContext->isAdminRoute($route_match->getRouteObject())) {
      return FALSE;
    }
    $route_entity = $this->getEntityFromRouteMatch($route_match);
    if (!$route_entity || !$route_entity instanceof ContentEntityInterface || !$this->getHierarchyFieldFromEntity($route_entity)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $route_entity */
    $route_entity = $this->getEntityFromRouteMatch($route_match);

    $entity_type = $route_entity->getEntityTypeId();
    $queryBuilder = $this->queryBuilderFactory->get($this->getHierarchyFieldFromEntity($route_entity), $entity_type);
    // Pass in the breadcrumb object for caching.
    $ancestors = $queryBuilder->findAncestors($route_entity)
      ->filter(function (Record $record) {
        if ($entity = $record->getEntity()) {
          return $entity->access('view label');
        }
        return FALSE;
      });

    $links = [];
    foreach ($ancestors as $ancestor) {
      $entity = $ancestor->getEntity();
      $breadcrumb->addCacheableDependency($entity);
      // Show just the label for the entity from the route.
      if ($entity->id() == $route_entity->id()) {
        $links[] = Link::createFromRoute($entity->label(), '<none>');
      }
      else {
        $links[] = $entity->toLink();
      }
    }

    array_unshift($links, Link::createFromRoute(new TranslatableMarkup('Home'), '<front>'));
    $breadcrumb->setLinks($links);
    return $breadcrumb;
  }

  /**
   * Return the entity type id from a route object.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route object.
   *
   * @return string|null
   *   The entity type id, null if it doesn't exist.
   */
  protected function getEntityTypeFromRoute(Route $route) {
    if (!empty($route->getOptions()['parameters'])) {
      foreach ($route->getOptions()['parameters'] as $option) {
        if (isset($option['type']) && strpos($option['type'], 'entity:') === 0) {
          return substr($option['type'], strlen('entity:'));
        }
      }
    }

    return NULL;
  }

  /**
   * Returns an entity parameter from a route match object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity, or null if it's not an entity route.
   */
  protected function getEntityFromRouteMatch(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteObject();
    if (!$route) {
      return NULL;
    }

    $entity_type_id = $this->getEntityTypeFromRoute($route);
    if ($entity_type_id) {
      return $route_match->getParameter($entity_type_id);
    }

    return NULL;
  }

  /**
   * Gets the field name to use for the hierarchy.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return string|null
   *   The field name, or null if there are no fields to use.
   */
  protected function getHierarchyFieldFromEntity(ContentEntityInterface $entity) {
    $fields = $this->entityFieldManager->getFieldMapByFieldType('entity_reference_hierarchy');
    $entity_type = $entity->getEntityTypeId();
    if (isset($fields[$entity_type])) {
      foreach ($fields[$entity_type] as $field_name => $detail) {
        if (!empty($detail['bundles'][$entity->bundle()])) {
          return $field_name;
        }
      }
    }

    return NULL;
  }

}
