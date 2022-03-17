<?php

namespace Drupal\onlyoffice_connector\Form;

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
    return 'onlyoffice_connector_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['onlyoffice_connector.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['doc_server_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Document Server Address'),
      '#default_value' => $this->config('onlyoffice_connector.settings')->get('doc_server_url'),
      '#required' => TRUE,
    ];
    $form['doc_server_jwt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Document Server JWT'),
      '#default_value' => $this->config('onlyoffice_connector.settings')->get('doc_server_jwt'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    //Todo: Validations settings
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('onlyoffice_connector.settings')
      ->set('doc_server_url', $form_state->getValue('doc_server_url'))
      ->set('doc_server_jwt', $form_state->getValue('doc_server_jwt'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
