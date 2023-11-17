<?php

namespace Drupal\entity_hierarchy\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_hierarchy\Storage\QueryBuilderFactory;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A handler to provide a field that show hierarchy depth of item.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("entity_hierarchy_tree_summary")
 */
class HierarchyTreeSummary extends FieldPluginBase {

  /**
   * Constructs a new HierarchyTreeSummary object.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected QueryBuilderFactory $queryBuilderFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_hierarchy.query_builder_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['summary_type'] = [
      'default' => 'child_counts',
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['summary_type'] = [
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => [
        'child_counts' => $this->t('Number of children at each level below'),
      ],
      '#title' => $this->t('Summary type'),
      '#default_value' => $this->options['summary_type'],
      '#description' => $this->t('Choose the summary type.'),
    ];
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if ($this->options['summary_type'] == 'child_counts') {
      $output = [];
      if ($entity = $this->getEntity($values)) {
        $queryBuilder = $this->queryBuilderFactory->get($this->configuration['entity_hierarchy_field'], $entity->getEntityTypeId());
        $descendants = $queryBuilder->findDescendants($entity);
        foreach ($descendants as $record) {
          $depth = $record->getDepth();
          if (empty($output[$depth])) {
            $output[$depth] = 0;
          }
          $output[$depth] = $output[$depth] + 1;
        }
        ksort($output);
      }
      return implode(' / ', $output);
    }

    return '';
  }

}
