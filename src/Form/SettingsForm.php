<?php

namespace Drupal\onlyoffice\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure ONLYOFFICE Connector settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'onlyoffice_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['onlyoffice.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['doc_server_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Document Editing Service address'),
      '#default_value' => $this->config('onlyoffice.settings')->get('doc_server_url'),
      '#required' => TRUE,
    ];
    $form['doc_server_jwt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret key (leave blank to disable)'),
      '#default_value' => $this->config('onlyoffice.settings')->get('doc_server_jwt'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('onlyoffice.settings')
      ->set('doc_server_url', $form_state->getValue('doc_server_url'))
      ->set('doc_server_jwt', $form_state->getValue('doc_server_jwt'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
