<?php

/**
 * @file
 * Contains \Drupal\entity_hierarchy\EventSubscriber\NodeHierarchyEventSubscriber.
 */

namespace Drupal\entity_hierarchy\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Page\DefaultHtmlPageRenderer;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

class NodeHierarchyEventSubscriber implements EventSubscriberInterface {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a NodeHierarchyEventSubscriber object.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * Initializes Entity Hierarchy module requirements.
   */
  public function onRequest(GetResponseEvent $event) {
    // Ensure we are not serving a cached page.
    if (function_exists('drupalSetContent')) {
      if ($this->moduleHandler->moduleExists('token')) {
        include_once DRUPAL_ROOT . '/' . libraries_get_path('module', 'entity_hierarchy') . '/includes/entity_hierarchy_token.inc';
      }
      if ($this->moduleHandler->moduleExists('workflow_ng')) {
        include_once DRUPAL_ROOT . '/' . libraries_get_path('module', 'entity_hierarchy') . '/includes/entity_hierarchy_workflow_ng.inc';
      }
    }
  }

  /**
   * Prevents page redirection so that the developer can see the intermediate debug data.
   * @param FilterResponseEvent $event
   */
  public function onResponse(FilterResponseEvent $event) {
    $response = $event->getResponse();

  }

  /**
   * Implements EventSubscriberInterface::getSubscribedEvents().
   *
   * @return array
   *   An array of event listener definitions.
   */
  static function getSubscribedEvents() {
    // Set a low value to start as early as possible.
    $events[KernelEvents::REQUEST][] = array('onRequest', -100);

    // Why only large positive value works here?
    $events[KernelEvents::RESPONSE][] = array('onResponse', 1000);

    return $events;
  }

}
