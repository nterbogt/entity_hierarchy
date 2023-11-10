<?php

declare(strict_types=1);

namespace Drupal\entity_hierarchy_microsite_test\Entity;

use Drupal\entity_hierarchy_microsite\Entity\Microsite;
use Drupal\node\Entity\Node;
use Drupal\entity_hierarchy\Storage\Record;

/**
 * Defines a class for a custom microsite entity.
 */
final class CustomMicrosite extends Microsite {

  /**
   * {@inheritdoc}
   */
  public function modifyMenuPluginDefinition(Record $record, Node $node, array $definition, Node $home): array {
    if ($record->getDepth() > \Drupal::state()->get('entity_hierarchy_microsite_max_depth', 100)) {
      return [];
    }
    return parent::modifyMenuPluginDefinition($record, $node, $definition, $home);
  }

}
