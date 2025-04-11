<?php

namespace Drupal\onlyoffice_form\Form;

/**
 * Copyright (c) Ascensio System SIA 2025.
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

use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Drupal\onlyoffice\OnlyofficeDocumentHelper;
use Drupal\onlyoffice\OnlyofficeUrlHelper;
use Drupal\onlyoffice_form\Ajax\OpenInNewTabCommand;
use Drupal\onlyoffice_form\OnlyofficeFormDocumentHelper;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Provides a form for creating a new ONLYOFFICE form.
 */
class OnlyofficeFormCreateForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The ONLYOFFICE form document helper.
   *
   * @var \Drupal\onlyoffice_form\OnlyofficeFormDocumentHelper
   */
  protected $documentHelper;

  /**
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The extension list module service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $extensionListModule;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new OnlyofficeFormCreateForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\onlyoffice_form\OnlyofficeFormDocumentHelper $document_helper
   *   The ONLYOFFICE form document helper.
   * @param \Drupal\file\FileUsage\DatabaseFileUsageBackend $file_usage
   *   The file usage service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
   *   The extension list module service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    LoggerChannelFactoryInterface $logger_factory,
    OnlyofficeFormDocumentHelper $document_helper,
    DatabaseFileUsageBackend $file_usage,
    FileSystemInterface $file_system,
    ModuleExtensionList $extension_list_module,
    LanguageManagerInterface $language_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->loggerFactory = $logger_factory;
    $this->documentHelper = $document_helper;
    $this->fileUsage = $file_usage;
    $this->fileSystem = $file_system;
    $this->extensionListModule = $extension_list_module;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('logger.factory'),
      $container->get('onlyoffice_form.document_helper'),
      $container->get('file.usage'),
      $container->get('file_system'),
      $container->get('extension.list.module'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'onlyoffice_form_create_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="onlyoffice-form-create-form-wrapper">';
    $form['#suffix'] = '</div>';

    // Attach the dialog library.
    $form['#attached']['library'][] = 'onlyoffice_form/onlyoffice_form.dialog';
    $form['#attached']['library'][] = 'onlyoffice_form/ajax_commands';

    // Get the current source value from form state or default to 'blank'.
    $source = $form_state->getValue('source', 'blank');

    $form['source'] = [
      '#type' => 'select',
      '#title' => $this->t('Create form from'),
      '#options' => [
        'blank' => $this->t('Blank'),
        'upload' => $this->t('Upload'),
      ],
      '#required' => TRUE,
      '#default_value' => $source,
      '#ajax' => [
        'callback' => '::updateFormElements',
        'wrapper' => 'onlyoffice-form-create-form-wrapper',
        'event' => 'change',
      ],
    ];

    // Name field - only visible for 'blank' option.
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => $source == 'blank',
      '#maxlength' => 255,
      '#access' => $source == 'blank',
    ];

    // File upload field - only visible for 'upload' option.
    $form['upload_file'] = [
      '#type' => 'managed_file',
      '#upload_location' => 'public://onlyoffice_forms/',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf'],
      ],
      '#required' => $source == 'upload',
      '#access' => $source == 'upload',
      '#attributes' => [
        'class' => ['onlyoffice-form-file-upload'],
      ],
      '#theme' => 'onlyoffice_form_file_upload',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Change button text based on source.
    $button_text = $this->t('Create');
    if ($source == 'upload') {
      $button_text = $this->t('Upload');
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $button_text,
      '#button_type' => 'primary',
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
      '#ajax' => [
        'callback' => '::submitAjaxForm',
        'wrapper' => 'onlyoffice-form-create-form-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Processing...'),
        ],
      ],
    ];

    // Add a validation handler.
    $form['#validate'][] = '::validateForm';

    return $form;
  }

  /**
   * Ajax callback to update form elements based on source selection.
   */
  public function updateFormElements(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Validates the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $source = $form_state->getValue('source');

    // Only validate PDF files when source is 'upload'.
    if ($source === 'upload') {
      // Get the file ID from the form state.
      $fid = $form_state->getValue('upload_file');

      // Handle array values (which happens with managed_file elements).
      if (is_array($fid)) {
        if (isset($fid['fids']) && is_array($fid['fids']) && !empty($fid['fids'])) {
          $fid = reset($fid['fids']);
        }
        elseif (!empty($fid)) {
          $fid = reset($fid);
        }
        else {
          $fid = NULL;
        }
      }

      if (!empty($fid)) {
        try {
          // Load the file entity.
          $file = $this->entityTypeManager->getStorage('file')->load($fid);

          if ($file) {
            // Check if the file is a PDF.
            $mime_type = $file->filemime->value;
            $filename = $file->filename->value;
            $extension = pathinfo($filename, PATHINFO_EXTENSION);

            if ($mime_type !== 'application/pdf' && strtolower($extension) !== 'pdf') {
              $this->loggerFactory->get('onlyoffice_form')->notice('Uploaded file is not a PDF: @mime', ['@mime' => $mime_type]);
              $form_state->setErrorByName('upload_file', $this->t('The uploaded file must be a PDF.'));
              return;
            }

            // Check if the file is a valid ONLYOFFICE form.
            $file_uri = $file->uri->value;
            $file_content = file_get_contents($file_uri);

            if (!$this->documentHelper->isOnlyofficeForm($file_content)) {
              $this->loggerFactory->get('onlyoffice_form')->notice('Uploaded file is not a valid ONLYOFFICE form');
              $form_state->setErrorByName('upload_file', $this->t('The uploaded file is not a valid ONLYOFFICE form.'));
            }
          }
        }
        catch (\Exception $e) {
          $this->loggerFactory->get('onlyoffice_form')->error('Error validating PDF form: @error', ['@error' => $e->getMessage()]);
          $form_state->setErrorByName('upload_file', $this->t('Error validating PDF form: @error', ['@error' => $e->getMessage()]));
        }
      }
    }
  }

  /**
   * Ajax callback for the form submission.
   */
  public function submitAjaxForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // Get the source value.
    $source = $form_state->getValue('source');

    // Check for validation errors and display them in the modal.
    if ($form_state->hasAnyErrors()) {
      return $this->replaceFormInModal($form, $form_state);
    }

    // Handle the blank option.
    if ($source === 'blank') {
      $name = $form_state->getValue('name');

      try {
        // Get the module path.
        $language = $this->languageManager->getCurrentLanguage()->getId();
        $template_path = OnlyofficeDocumentHelper::getNewTemplatePath('pdf', $language);

        // Create the destination directory if it doesn't exist.
        $directory = 'public://onlyoffice_forms/';
        $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

        // Generate a safe filename.
        $filename = $name;
        if (!str_ends_with(strtolower($filename), '.pdf')) {
          $filename .= '.pdf';
        }
        $destination = $directory . $this->fileSystem->basename($filename);

        // Copy the template file.
        $uri = $this->fileSystem->copy($template_path, $destination, FileSystemInterface::EXISTS_RENAME);

        if ($uri) {
          // Create a file entity.
          $file = File::create([
            'uri' => $uri,
            'filename' => $this->fileSystem->basename($uri),
            'filemime' => 'application/pdf',
            'status' => File::STATUS_PERMANENT,
            'uid' => $this->currentUser->id(),
          ]);
          $file->save();

          // Get the media type that uses our source plugin.
          $media_types = $this->entityTypeManager->getStorage('media_type')
            ->loadByProperties(['source' => 'onlyoffice_pdf_form']);
          $media_type = reset($media_types);

          if (!$media_type) {
            throw new \Exception('Media type with onlyoffice_pdf_form source not found. Please install the module properly or run the update hooks.');
          }

          // Get the source field name from the configuration.
          $source_configuration = $media_type->get('source_configuration');
          if (empty($source_configuration) || empty($source_configuration['source_field'])) {
            throw new \Exception('Source field not properly configured in media type');
          }
          $field_name = $source_configuration['source_field'];

          // Create a new Media entity.
          $media = Media::create([
            'bundle' => $media_type->id(),
            'uid' => $this->currentUser->id(),
            'name' => $name,
            $field_name => [
              'target_id' => $file->id(),
              'display' => 1,
              'description' => '',
            ],
          ]);
          $media->save();

          // Set file usage to prevent it from being deleted during cron.
          $this->fileUsage->add($file, 'onlyoffice_form', 'media', $media->id());

          // Add a success message.
          $response->addCommand(new MessageCommand($this->t('Blank PDF form has been created successfully.'), NULL, ['type' => 'status']));

          // Close the modal dialog.
          $response->addCommand(new CloseModalDialogCommand());

          // Get the editor URL and open it in a new tab.
          $editorUrl = OnlyofficeUrlHelper::getEditorUrl($media)->toString();
          $response->addCommand(new OpenInNewTabCommand($editorUrl));

          // Redirect to the PDF form collection page.
          $url = Url::fromRoute('entity.onlyoffice_form.collection');
          $response->addCommand(new RedirectCommand($url->toString()));

          return $response;
        }
        else {
          $this->loggerFactory->get('onlyoffice_form')->error('Could not copy template file to @destination', ['@destination' => $destination]);
          // Set form error instead of using MessageCommand.
          $form_state->setErrorByName('name', $this->t('Could not create the blank form.'));
          return $this->replaceFormInModal($form, $form_state);
        }
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('onlyoffice_form')->error('Error creating blank PDF form: @message', ['@message' => $e->getMessage()]);
        // Set form error instead of using MessageCommand.
        $form_state->setErrorByName('name', $this->t('An error occurred while creating the blank PDF form: @error', ['@error' => $e->getMessage()]));
        return $this->replaceFormInModal($form, $form_state);
      }
    }
    // Handle the upload scenario.
    elseif ($source === 'upload') {
      // Get the file ID from the form state.
      $fid = $form_state->getValue('upload_file');

      // Handle array values (which happens with managed_file elements).
      if (is_array($fid)) {
        if (isset($fid['fids']) && is_array($fid['fids']) && !empty($fid['fids'])) {
          $fid = reset($fid['fids']);
        }
        elseif (!empty($fid)) {
          $fid = reset($fid);
        }
        else {
          $fid = NULL;
        }
      }

      $this->loggerFactory->get('onlyoffice_form')->notice('Extracted file ID: @fid', ['@fid' => $fid]);

      try {
        // Load the file entity.
        $file = $this->entityTypeManager->getStorage('file')->load($fid);

        if ($file) {
          // Make the file permanent.
          $file->setPermanent();
          $file->save();

          // Get the media type that uses our source plugin.
          $media_types = $this->entityTypeManager->getStorage('media_type')
            ->loadByProperties(['source' => 'onlyoffice_pdf_form']);
          $media_type = reset($media_types);

          if (!$media_type) {
            throw new \Exception('Media type with onlyoffice_pdf_form source not found. Please install the module properly or run the update hooks.');
          }

          // Get the source field name from the configuration.
          $source_configuration = $media_type->get('source_configuration');
          if (empty($source_configuration) || empty($source_configuration['source_field'])) {
            throw new \Exception('Source field not properly configured in media type');
          }
          $field_name = $source_configuration['source_field'];

          // Create a new Media entity.
          $media = Media::create([
            'bundle' => $media_type->id(),
            'uid' => $this->currentUser->id(),
            'name' => $file->getFilename(),
            $field_name => [
              'target_id' => $file->id(),
              'display' => 1,
              'description' => '',
            ],
          ]);

          // Save the entity.
          $media->save();

          // Set file usage to prevent it from being deleted during cron.
          $this->fileUsage->add($file, 'onlyoffice_form', 'media', $media->id());

          // Add a success message.
          $response->addCommand(new MessageCommand($this->t('PDF form has been uploaded successfully.'), NULL, ['type' => 'status']));

          // Close the modal dialog.
          $response->addCommand(new CloseModalDialogCommand());

          // Get the editor URL and open it in a new tab.
          $editorUrl = OnlyofficeUrlHelper::getEditorUrl($media)->toString();
          $response->addCommand(new OpenInNewTabCommand($editorUrl));

          // Redirect to the PDF form collection page.
          $url = Url::fromRoute('entity.onlyoffice_form.collection');
          $response->addCommand(new RedirectCommand($url->toString()));

          return $response;
        }
        else {
          $this->loggerFactory->get('onlyoffice_form')->error('Could not load file with ID: @fid', ['@fid' => $fid]);
          // Set form error instead of using MessageCommand.
          $form_state->setErrorByName('upload_file', $this->t('Could not load the uploaded file.'));
          return $this->replaceFormInModal($form, $form_state);
        }
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('onlyoffice_form')->error('Error saving PDF form: @message', ['@message' => $e->getMessage()]);
        // Set form error instead of using MessageCommand.
        $form_state->setErrorByName('upload_file', $this->t('An error occurred while saving the PDF form: @error', ['@error' => $e->getMessage()]));
        return $this->replaceFormInModal($form, $form_state);
      }
    }
    else {
      // For other sources, just close the dialog for now.
      $response->addCommand(new CloseModalDialogCommand());
    }

    return $response;
  }

  /**
   * Replaces the form in the modal with version that includes error messages.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  protected function replaceFormInModal(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // Add status messages to the top of the form.
    $form = [
      'status_messages' => [
        '#type' => 'status_messages',
        '#weight' => -1000,
      ],
    ] + $form;

    // Get the form wrapper ID.
    $wrapper_id = 'onlyoffice-form-create-form-wrapper';

    // Replace the form content in the modal.
    $response->addCommand(new HtmlCommand('#' . $wrapper_id, $form));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This method is required but not used for Ajax forms
    // The actual submission is handled in submitAjaxForm()
  }

}
