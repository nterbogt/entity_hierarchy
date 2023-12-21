<?php

namespace Drupal\entity_hierarchy_microsite\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_hierarchy\Storage\Record;
use Drupal\node\Entity\Node;

/**
 * Defines an interface for microsites.
 */
interface MicrositeInterface extends ContentEntityInterface {

  /**
   * Gets the home page of the microsite.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Home page node, or null if none exists.
   */
  public function getHome();

  /**
   * Gets the logo of the microsite.
   *
   * @return \Drupal\media\MediaInterface|null
   *   Logo media, or null if none exists.
   */
  public function getLogo();

  /**
   * Whether the microsite should generate a menu.
   *
   * @return bool
   *   True if a menu should be built, false otherwise.
   */
  public function shouldGenerateMenu();

  /**
   * Allows a microsite sub-class to modify plugin definitions.
   *
   * @param \Drupal\entity_hierarchy\Storage\Record $record
   *   The entity hierarchy record.
   * @param \Drupal\node\Entity\Node $node
   *   Drupal node for the menu plugin definition.
   * @param array $definition
   *   Menu plugin definition. This should be modified and returned.
   * @param \Drupal\node\Entity\Node $home
   *   Drupal node for the microsite home.
   *
   * @return array
   *   Modified menu plugin definition. To prevent the menu link from being
   *   created, return an empty array.
   */
  public function modifyMenuPluginDefinition(Record $record, Node $node, array $definition, Node $home): array;

}
