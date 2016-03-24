<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\Plugin\views\display\HierarchyEmbed.
 */

use Drupal\views\Plugin\views\display\Block;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
//use Drupal\views\Plugin\Block\ViewsBlock;
use Drupal\views\Plugin\views\display\ResponseDisplayPluginInterface;


namespace Drupal\nodehierarchy\Plugin\views\display;

/**
 * The plugin that handles an HierarchyEmbed display.
 *
 * "hierarchy_embed_display" is a custom property, used with
 * \Drupal\views\Views::getApplicableViews() to retrieve all views with a
 * 'Hierarchy Embed' display.
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "hierarchy_embed",
 *   title = @Translation("Hierarchy Embed"),
 *   admin = @Translation("Hierarchy Embed Source"),
 *   help = @Translation("Provides displays that may be embedded on hierarchy pages."),
 *   theme = "views_view",
 *   register_theme = FALSE,
 *   uses_menu_links = FALSE,
 *   hierarchy_embed_display = TRUE
 * )
 */
class HierarchyEmbed extends Block implements ResponseDisplayPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['displays'] = array('default' => array());
    return $options;
  }

  /**
   * Provide the summary for page options in the views UI.
   *
   * This output is returned as an array.
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    $categories['nodehierarchy_embed'] = array(
      'title' => t('Embed settings'),
      'column' => 'second',
      'build' => array(
        '#weight' => -10,
      ),
    );

    $name = strip_tags($this->get_option('nodehierarchy_embed_admin_name'));
    if (empty($name)) {
      $name = t('None');
    }
//    $block_category = $this->getOption('block_category');

    $options['nodehierarchy_embed_admin_name'] = array(
      'category' => 'nodehierarchy_embed',
      'title' => t('Admin name'),
      'value' => views_ui_truncate($name, 24),
    );
  }

  /**
   * Provide the default form for setting options.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    switch ($form_state->get('section')) {
      case 'nodehierarchy_embed_admin_name':
        $form['#title'] .= t('Embed admin name');
        $form['nodehierarchy_embed_admin_name'] = array(
          '#type' => 'textfield',
          '#description' => t('This will appear as the name of this embed in the node edit screen.'),
          '#default_value' => $this->get_option('nodehierarchy_embed_admin_name'),
        );
        break;
    }
  }

  /**
   * Perform any necessary changes to the form values prior to storage.
   * There is no need for this function to actually store the data.
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    $section = $form_state->get('section');
    switch ($section) {
      case 'nodehierarchy_embed_admin_name':
        $this->set_option('nodehierarchy_embed_admin_name', $form_state['values']['nodehierarchy_embed_admin_name']);
        break;
    }
  }

  /**
   * The display block handler returns the structure necessary for a block.
   */
  public function execute() {
    // Prior to this being called, the $view should already be set to this
    // display, and arguments should be set on the view.
    parent::execute();
    if (!isset($this->view->override_path)) {
      $this->view->override_path = 'node';
    }

    $data = $this->view->render();
    if (!empty($this->view->result) || $this->get_option('empty') || !empty($this->view->style_plugin->definition['even empty'])) {
      return $data;
    }
  }
}
