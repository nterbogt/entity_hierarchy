<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\EventSubscriber\NodeHierarchyEventSubscriber.
 */

namespace Drupal\nodehierarchy\EventSubscriber;

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
   * Initializes Node Hierarchy module requirements.
   */
  public function onRequest(GetResponseEvent $event) {
    // Ensure we are not serving a cached page.
    if (function_exists('drupalSetContent')) {
      if ($this->moduleHandler->moduleExists('token')) {
        include_once DRUPAL_ROOT . '/' . libraries_get_path('module', 'nodehierarchy') . '/includes/nodehierarchy_token.inc';
      }
      if ($this->moduleHandler->moduleExists('workflow_ng')) {
        include_once DRUPAL_ROOT . '/' . libraries_get_path('module', 'nodehierarchy') . '/includes/nodehierarchy_workflow_ng.inc';
      }
    }
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
