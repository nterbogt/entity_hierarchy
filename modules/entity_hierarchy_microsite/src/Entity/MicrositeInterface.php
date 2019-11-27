<?php

namespace Drupal\entity_hierarchy_microsite\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines an interface for microsites.
 */
interface MicrositeInterface extends ContentEntityInterface {

  /**
   * Gets the home page of the microsite.
   *
   * @return \Drupal\node\NodeInterface
   *   Home page node.
   */
  public function getHome();

  /**
   * Gets the logo of the microsite.
   *
   * @return \Drupal\media\MediaInterface
   *   Home page node.
   */
  public function getLogo();

}
