<?php

/**
 * @file
 * Contains \Drupal\entity_hierarchy\Form\AdminSettingsForm.
 */

namespace Drupal\entity_hierarchy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form for Entity Hierarchy Admin settings.
 */
class AdminSettingsForm extends ConfigFormBase {

  /**
   * The list of node types (content types)
   *
   * @var array
   */
  protected $node_types;

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'entity_hierarchy_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['entity_hierarchy.settings'];
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->node_types = node_type_get_names();
//    $config = $this->config('entity_hierarchy.settings');
    $hierarchy_manager = \Drupal::service('entity_hierarchy.manager');

    // Individual type settings.
    $form['entity_hierarchy_types'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Node Type Settings'),
      '#description' => $this->t('Settings for individual node types. These can also be individually set for each content type.'),
    );
    foreach ($this->node_types as $key => $type) {
      // Individual type settings.
      $form['entity_hierarchy_types'][$key] = array(
        '#type' => 'details',
        '#title' => $type,
        '#open' => FALSE,
      );
      $form['entity_hierarchy_types'][$key] += $hierarchy_manager->hierarchyGetNodeTypeSettingsForm($key, TRUE);
    }

    // Menu generation. Todo: implement later
//    $form['entity_hierarchy_menu'] = array(
//      '#type' => 'fieldset',
//      '#title' => t('Entity Hierarchy Menu Generation'),
//    );
//    $form['entity_hierarchy_menu']['entity_hierarchy_default_menu_name'] = array(
//      '#type' => 'select',
//      '#title' => t('Default parent menu'),
//      '#options' => array_keys(entity_load_multiple('menu')),
//      '#default_value' => $config->get('entity_hierarchy_default_menu_name'),
//      '#description' => t('If a menu is created for a node with no parent the new menu item will appear in this menu.'),
//    );
//
//    $form['entity_hierarchy_menu']['entity_hierarchy_menu_module_edit'] = array(
//      '#type' => 'checkbox',
//      '#title' => t('Always show hidden Entity Hierarchy menu items on the menu overview forms.'),
//      '#default_value' => $config->get('entity_hierarchy_menu_module_edit'),
//      '#description' => t('Allow disabled entity_hierarchy menu items to be edited with regular menu items in the menu overview screen. Turn this off if large Entity Hierarchy menus are causing memory errors on menu edit screens.'),
//    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->config('entity_hierarchy.settings');
    foreach ($this->node_types as $key => $type){
      $config->set('nh_allowchild_'.$key, $values['nh_allowchild_'.$key]);
//      $config->set('nh_createmenu_'.$key, $values['nh_createmenu_'.$key]);
//      $config->set('nh_multiple_'.$key, $values['nh_multiple_'.$key]);
      $config->set('nh_defaultparent_'.$key, $values['nh_defaultparent_'.$key]);
//      $config->set('entity_hierarchy_default_menu_name', $values['entity_hierarchy_default_menu_name']);
//      $config->set('entity_hierarchy_menu_module_edit', $values['entity_hierarchy_menu_module_edit']);
    }
    $config->save();

    // Would have preferred to handle this in the entity_hierarchy_views module, but not sure how
    // Perhaps can override this form?
    if (\Drupal::moduleHandler()->moduleExists('entity_hierarchy_views')) {
      $views_config = $this->config('entity_hierarchy.settings');
      foreach ($this->node_types as $key => $type) {
        $views_config->set('nh_default_children_view_'.$key, $values['nh_default_children_view_'.$key]);
      }
      $views_config->save();
    }

    parent::submitForm($form, $form_state);
  }

}


