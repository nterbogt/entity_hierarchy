<?php

/**
 * @file
 * Contains \Drupal\nodehierarchy\Form\AdminSettingsForm.
 */

namespace Drupal\nodehierarchy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form for Node Hierarchy Admin settings.
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
    return 'nodehierarchy_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['nodehierarchy.settings'];
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->node_types = node_type_get_names();
    $config = $this->config('nodehierarchy.settings');
    $hierarchy_manager = \Drupal::service('nodehierarchy.manager');

    // Individual type settings.
    $form['nodehierarchy_types'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Node Type Settings'),
      '#description' => $this->t('Settings for individual node types. These can also be individually set for each content type.'),
    );
    foreach ($this->node_types as $key => $type) {
      // Individual type settings.
      $form['nodehierarchy_types'][$key] = array(
        '#type' => 'details',
        '#title' => $type,
        '#open' => FALSE,
      );
      $form['nodehierarchy_types'][$key] += $hierarchy_manager->hierarchyGetNodeTypeSettingsForm($key, TRUE);
    }

    // Menu generation.
    $form['nodehierarchy_menu'] = array(
      '#type' => 'fieldset',
      '#title' => t('Node Hierarchy Menu Generation'),
    );
    $form['nodehierarchy_menu']['nodehierarchy_default_menu_name'] = array(
      '#type' => 'select',
      '#title' => t('Default parent menu'),
      '#options' => array_keys(entity_load_multiple('menu')),
      '#default_value' => $config->get('nodehierarchy_default_menu_name'),
      '#description' => t('If a menu is created for a node with no parent the new menu item will appear in this menu.'),
    );

    $form['nodehierarchy_menu']['nodehierarchy_menu_module_edit'] = array(
      '#type' => 'checkbox',
      '#title' => t('Always show hidden Node Hierarchy menu items on the menu overview forms.'),
      '#default_value' => $config->get('nodehierarchy_menu_module_edit'),
      '#description' => t('Allow disabled nodehierarchy menu items to be edited with regular menu items in the menu overview screen. Turn this off if large Node Hierarchy menus are causing memory errors on menu edit screens.'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // dpm($values);
    // dpm($values['nh_defaultparent_'.$key]);
    $config = $this->config('nodehierarchy.settings');
    foreach ($this->node_types as $key => $type){
      $config->set('nh_allowchild_'.$key, $values['nh_allowchild_'.$key]);
      $config->set('nh_createmenu_'.$key, $values['nh_createmenu_'.$key]);
      $config->set('nh_multiple_'.$key, $values['nh_multiple_'.$key]);
      $config->set('nh_defaultparent_'.$key, $values['nh_defaultparent_'.$key]);
      $config->set('nodehierarchy_default_menu_name', $values['nodehierarchy_default_menu_name']);
      $config->set('nodehierarchy_menu_module_edit', $values['nodehierarchy_menu_module_edit']);
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}


