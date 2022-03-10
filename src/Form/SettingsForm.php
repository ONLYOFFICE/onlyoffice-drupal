<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2022
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

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
      '#title' => $this->t('Document Editing Service address'),
      '#default_value' => $this->config('onlyoffice_connector.settings')->get('doc_server_url'),
      '#required' => TRUE,
    ];
    $form['doc_server_jwt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret key (leave blank to disable)'),
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
