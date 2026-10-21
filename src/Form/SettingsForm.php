<?php

namespace Drupal\onlyoffice\Form;

/**
 * Copyright (c) Ascensio System SIA 2026.
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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;

/**
 * Configure ONLYOFFICE Connector settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The document service.
   *
   * @var \Drupal\onlyoffice\OnlyofficeDocumentService
   */
  protected $documentService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->documentService = $container->get('onlyoffice.document_service');

    return $instance;
  }

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
      '#type' => 'password',
      '#title' => $this->t('Secret key (leave blank to disable)'),
      '#default_value' => $this->config('onlyoffice.settings')->get('doc_server_jwt'),
      '#attributes' => [
        'autocomplete' => 'new-password',
        'data-onlyoffice-secret' => '',
      ],
      '#after_build' => [[static::class, 'renderSecretValue']],
    ];
    $form['#attached']['library'][] = 'onlyoffice/onlyoffice.settings';

    $advancedOpen = $this->config('onlyoffice.settings')->get('doc_server_jwt_header')
      || $this->config('onlyoffice.settings')->get('doc_server_internal_url')
      || $this->config('onlyoffice.settings')->get('storage_url');

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced server settings'),
      '#open' => $advancedOpen,
    ];
    $form['advanced']['doc_server_jwt_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authorization header (leave blank to use default header)'),
      '#default_value' => $this->config('onlyoffice.settings')->get('doc_server_jwt_header'),
      '#placeholder' => 'Authorization',
    ];
    $form['advanced']['doc_server_internal_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ONLYOFFICE Docs address for internal requests from the server'),
      '#default_value' => $this->config('onlyoffice.settings')->get('doc_server_internal_url'),
    ];
    $form['advanced']['storage_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server address for internal requests from ONLYOFFICE Docs'),
      '#default_value' => $this->config('onlyoffice.settings')->get('storage_url'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * Render the current secret into the password field, which core omits.
   */
  public static function renderSecretValue(array $element, FormStateInterface $form_state) {
    $element['#attributes']['value'] = $element['#value'];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $validator = Validation::createValidator();

    // Allow single-level host names, such as Docker service names.
    $constraint = property_exists(Url::class, 'requireTld') ? new Url(requireTld: FALSE) : new Url();

    foreach (['doc_server_url', 'doc_server_internal_url', 'storage_url'] as $name) {
      $value = trim((string) $form_state->getValue($name));
      $form_state->setValue($name, $value);

      $errors = $validator->validate($value, $constraint);

      if (count($errors) > 0) {
        $messages = [];

        foreach ($errors as $error) {
          $messages[] = $error->getMessage();
        }

        $form_state->setErrorByName($name, implode("\n", $messages));
      }
      elseif (strpbrk($value, '?#') !== FALSE) {
        $form_state->setErrorByName($name, $this->t('The address must not contain a query or a fragment.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $docsUrl = rtrim($form_state->getValue('doc_server_url'), "/");
    $docsInternalUrl = rtrim($form_state->getValue('doc_server_internal_url'), "/");
    $storageUrl = rtrim($form_state->getValue('storage_url'), "/");

    $this->config('onlyoffice.settings')
      ->set('doc_server_url', $docsUrl)
      ->set('doc_server_jwt', $form_state->getValue('doc_server_jwt'))
      ->set('doc_server_jwt_header', trim($form_state->getValue('doc_server_jwt_header')))
      ->set('doc_server_internal_url', $docsInternalUrl)
      ->set('storage_url', $storageUrl)
      ->save();
    parent::submitForm($form, $form_state);

    // The check result is informational only, the settings are saved anyway.
    try {
      $version = $this->documentService->checkConnection();
      $this->messenger()->addStatus($this->t('ONLYOFFICE Docs connection check succeeded (version @version).', ['@version' => $version]));
    }
    catch (\RuntimeException $e) {
      $this->logger('onlyoffice')->warning('Connection check failed: @error', ['@error' => $e->getMessage()]);
      $this->messenger()->addWarning($this->t('ONLYOFFICE Docs connection check failed: @error', ['@error' => $e->getMessage()]));
    }
  }

}
