<?php

declare(strict_types=1);

namespace Drupal\entity_hierarchy\Information;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\entity_hierarchy\Storage\RecordCollection;

/**
 * Defines a value object for a child entity warning.
 *
 * @see entity_hierarchy_form_alter()
 */
class ChildEntityWarning {

  /**
   * Constructs a new ChildEntityWarning object.
   *
   * @param \Drupal\entity_hierarchy\Storage\RecordCollection $records
   *   Related entity hierarchy records.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cache
   *   Cache metadata.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $parent
   *   (optional) Parent if exists.
   */
  public function __construct(
    protected readonly RecordCollection $records,
    protected readonly RefinableCacheableDependencyInterface $cache,
    protected readonly ?ContentEntityInterface $parent = NULL
  ) {}

  /**
   * Gets render array for child entity list.
   *
   * @return array
   *   Render array.
   */
  public function getList() {
    $child_labels = [];
    $build = ['#theme' => 'item_list'];
    foreach ($this->records as $record) {
      $child_labels[] = $record->getEntity()->label();
    }
    $build['#items'] = array_unique($child_labels);
    $this->cache->applyTo($build);
    return $build;
  }

  /**
   * Gets warning message for deleting a parent.
   *
   * @return \Drupal\Core\StringTranslation\PluralTranslatableMarkup
   *   Warning message.
   */
  public function getWarning() {
    if ($this->parent) {
      return new PluralTranslatableMarkup(
        // Related entities includes the parent, so we remove that.
        count($this->records),
        'This Test entity has 1 child, deleting this item will change its parent to be @parent.',
        'This Test entity has @count children, deleting this item will change their parent to be @parent.',
        [
          '@parent' => $this->parent->label(),
        ]);
    }
    return new PluralTranslatableMarkup(
      count($this->records),
      'This Test entity has 1 child, deleting this item will move that item to the root of the hierarchy.',
      'This Test entity has @count children, deleting this item will move those items to the root of the hierarchy.');
  }

}
