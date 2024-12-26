<?php

namespace Drupal\onlyoffice\Form;

/**
 * Copyright (c) Ascensio System SIA 2023.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;

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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('doc_server_url');

    $validator = Validation::createValidator();
    $errors = $validator->validate($url, new Url());

    if (count($errors) > 0) {
      $messages = [];

      foreach($errors as $error) {
        $messages[] = $error->getMessage();        
      }

      $form_state->setErrorByName('doc_server_url', implode("\n", $messages));
    }
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
