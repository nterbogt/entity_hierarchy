<?php

namespace Drupal\entity_hierarchy_microsite;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\Form\MenuLinkDefaultForm;
use Drupal\Core\Menu\MenuTreeStorage;
use Drupal\entity_hierarchy\Information\ParentCandidateInterface;
use Drupal\entity_hierarchy\Storage\QueryBuilderFactory;
use Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface;
use Drupal\entity_hierarchy_microsite\Form\MicrositeMenuItemForm;
use Drupal\entity_hierarchy_microsite\Plugin\Menu\MicrositeMenuItem;

/**
 * Defines a class for microsite menu link discovery.
 */
class MicrositeMenuLinkDiscovery implements MicrositeMenuLinkDiscoveryInterface {

  /**
   * Constructs a new MicrositeMenuItemDeriver.
   *
   * @param \Drupal\entity_hierarchy\Information\ParentCandidateInterface $candidate
   *   Candidate.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\entity_hierarchy\Storage\QueryBuilderFactory $queryBuilderFactory
   *   Query builder factory.
   */
  public function __construct(
    protected ParentCandidateInterface $candidate,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected QueryBuilderFactory $queryBuilderFactory
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinkDefinitions(MicrositeInterface $microsite = NULL) {
    $definitions = [];
    $microsites = $microsite ? [$microsite] : $this->entityTypeManager->getStorage('entity_hierarchy_microsite')->loadMultiple();
    /** @var \Drupal\entity_hierarchy_microsite\Entity\MicrositeInterface $microsite */
    foreach ($microsites as $microsite) {
      $home = $microsite->getHome();
      $generate_menu = $microsite->shouldGenerateMenu();
      if (!$home || !$generate_menu) {
        continue;
      }
      $parentUuids = [];
      foreach ($this->candidate->getCandidateFields($home) as $field_name) {
        $queryBuilder = $this->queryBuilderFactory->get($field_name, 'node');
        // Relies on order of children. So buildTree to iterate in parent order.
        $children = $queryBuilder->findDescendants($home)
          ->buildTree();
        if (empty($children)) {
          // No children.
          continue;
        }
        $url = $home->toUrl();
        $definitions[$home->uuid()] = [
          'class' => MicrositeMenuItem::class,
          'menu_name' => 'entity-hierarchy-microsite',
          'route_name' => $url->getRouteName(),
          'route_parameters' => $url->getRouteParameters(),
          'options' => $url->getOptions(),
          'title' => $home->label(),
          'description' => '',
          'weight' => $microsite->id(),
          'id' => 'entity_hierarchy_microsite:' . $home->uuid(),
          'metadata' => [
            'entity_id' => $home->id(),
            'entity_hierarchy_depth' => $queryBuilder->findDepth($home),
          ],
          'form_class' => MenuLinkDefaultForm::class,
          'enabled' => 1,
          'expanded' => 1,
          'provider' => 'entity_hierarchy_microsite',
          'discovered' => 1,
        ];
        /** @var \Drupal\entity_hierarchy\Storage\Record $record */
        foreach ($children as $record) {
          $item = $record->getEntity();
          if (empty($item) || $record->getDepth() >= MenuTreeStorage::MAX_DEPTH) {
            continue;
          }
          $url = $item->toUrl();
          $revisionKey = sprintf('%s:%s', $record->getId(), $record->getRevisionId());
          $itemUuid = $item->uuid();
          $parentUuids[$revisionKey] = $itemUuid;
          if ($definition = $microsite->modifyMenuPluginDefinition($record, $item, [
            'class' => MicrositeMenuItem::class,
            'menu_name' => 'entity-hierarchy-microsite',
            'route_name' => $url->getRouteName(),
            'route_parameters' => $url->getRouteParameters(),
            'options' => $url->getOptions(),
            'title' => $item->label(),
            'description' => '',
            'weight' => $record->getWeight(),
            'id' => 'entity_hierarchy_microsite:' . $itemUuid,
            'metadata' => [
              'entity_id' => $item->id(),
              'entity_hierarchy_depth' => $record->getDepth(),
            ],
            'form_class' => MenuLinkDefaultForm::class,
            'enabled' => 1,
            'expanded' => 1,
            'provider' => 'entity_hierarchy_microsite',
            'discovered' => 1,
            'parent' => 'entity_hierarchy_microsite:' . $home->uuid(),
          ], $home)) {
            $definitions[$itemUuid] = $definition;
            $parent = $queryBuilder->findParent($item);
            if ($parent && ($parentRevisionKey = sprintf('%s:%s', $parent->id(), $parent->getRevisionId())) && array_key_exists($parentRevisionKey, $parentUuids)) {
              $definitions[$itemUuid]['parent'] = 'entity_hierarchy_microsite:' . $parentUuids[$parentRevisionKey];
            }
          }
        }
      }
      /** @var \Drupal\entity_hierarchy_microsite\Entity\MicrositeMenuItemOverrideInterface $override */
      if ($definitions) {
        foreach ($this->entityTypeManager->getStorage('eh_microsite_menu_override')
          ->loadByProperties([
            'target' => array_keys($definitions),
          ]) as $override) {
          $original = $definitions[$override->getTarget()];
          $definitions[$override->getTarget()] = [
            'metadata' => [
              'original' => array_intersect_key($original, [
                'title' => TRUE,
                'weight' => TRUE,
                'enabled' => TRUE,
                'expanded' => TRUE,
                'parent' => TRUE,
              ]),
            ] + $original['metadata'],
            'title' => $override->label(),
            'form_class' => MicrositeMenuItemForm::class,
            'weight' => $override->getWeight(),
            'enabled' => $override->isEnabled(),
            'expanded' => $override->isExpanded(),
            'parent' => $override->getParent(),
          ] + $original;
        }
      }
    }

    $this->moduleHandler->alter('entity_hierarchy_microsite_links', $definitions);

    return $definitions;
  }

}
