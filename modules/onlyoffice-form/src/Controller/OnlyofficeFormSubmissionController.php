<?php

namespace Drupal\onlyoffice_form\Controller;

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

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\onlyoffice\OnlyofficeAppConfig;
use Drupal\onlyoffice\OnlyofficeDocumentHelper;
use Drupal\onlyoffice\OnlyofficeUrlHelper;
use Drupal\onlyoffice_form\Entity\OnlyofficeFormSubmission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for handling form submission operations.
 */
class OnlyofficeFormSubmissionController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The onlyoffice document helper service.
   *
   * @var \Drupal\onlyoffice\OnlyofficeDocumentHelper
   */
  protected $documentHelper;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The list of available modules.
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
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new OnlyofficeFormSubmissionController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    FileSystemInterface $file_system,
    OnlyofficeDocumentHelper $document_helper,
    DateFormatterInterface $date_formatter,
    ModuleExtensionList $extension_list_module,
    LanguageManagerInterface $language_manager,
    RendererInterface $renderer
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->fileSystem = $file_system;
    $this->documentHelper = $document_helper;
    $this->dateFormatter = $date_formatter;
    $this->extensionListModule = $extension_list_module;
    $this->languageManager = $language_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('file_system'),
      $container->get('onlyoffice.document_helper'),
      $container->get('date.formatter'),
      $container->get('extension.list.module'),
      $container->get('language_manager'),
      $container->get('renderer')
    );
  }

  /**
   * Downloads a submission file.
   *
   * @param \Drupal\onlyoffice_form\Entity\OnlyofficeFormSubmission $onlyoffice_form_submission
   *   The submission entity.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The file download response.
   */
  public function downloadFile(OnlyofficeFormSubmission $onlyoffice_form_submission) {
    // Get the file from the submission
    $file = $onlyoffice_form_submission->getFile();
    
    if (!$file) {
      throw new NotFoundHttpException('File not found');
    }
    
    // Get the file URI
    $uri = $file->getFileUri();
    
    // Check if the file exists
    if (!file_exists($uri)) {
      throw new NotFoundHttpException('File not found');
    }
    
    // Get the file name for download
    $filename = $file->getFilename();
    
    // Create a binary file response
    $response = new BinaryFileResponse($uri);
    
    // Set the content disposition to force download
    $response->setContentDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename
    );
    
    return $response;
  }

  /**
   * View a submission file.
   *
   * @param \Drupal\onlyoffice_form\Entity\OnlyofficeFormSubmission $onlyoffice_form_submission
   *   The submission entity.
   *
   * @return array
   *   The file download response.
   */
  public function view(OnlyofficeFormSubmission $onlyoffice_form_submission, Request $request) {
    $editorType = 'desktop';

    if (preg_match(OnlyofficeAppConfig::USER_AGENT_MOBILE, $request->headers->get('User-Agent'))) {
      $editorType = 'mobile';
    }

    $file = $onlyoffice_form_submission->getFile();
    
    if (!$file) {
      $this->messenger()->addError($this->t('File not found.'));
      return [
        '#markup' => $this->t('The file associated with this submission could not be found.'),
        '#prefix' => '<div class="messages messages--error">',
        '#suffix' => '</div>',
      ];
    }
    
    // Check if the file exists on disk
    $uri = $file->getFileUri();
    if (!file_exists($uri)) {
      $this->messenger()->addError($this->t('File not found on disk.'));
      return [
        '#markup' => $this->t('The file associated with this submission exists in the database but could not be found on disk.'),
        '#prefix' => '<div class="messages messages--error">',
        '#suffix' => '</div>',
      ];
    }
    
    $extension = OnlyofficeDocumentHelper::getExtension($file->getFilename());
    $documentType = OnlyofficeDocumentHelper::getDocumentType($extension);

    $context = [
      '@type' => $extension,
      '%label' => $file->getFilename(),
    ];

    if (!$documentType) {
      $this->getLogger('onlyoffice')->warning('File @type %label is not supported current module.', $context);
      return [
        '#markup' => $this->t("Sorry, this file format isn't supported (@extension)", ['@extension' => $extension]),
        '#prefix' => '<div class="messages messages--error">',
        '#suffix' => '</div>',
      ];
    }

    $user = $this->currentUser();

    $editorConfig = $this->documentHelper->createEditorConfig(
          $editorType,
          $this->documentHelper->getEditingKey($file),
          $file->getFilename(),
          OnlyofficeUrlHelper::getDownloadFileUrl($file),
          $user->getDisplayName(),
          $this->dateFormatter->format($file->getCreatedTime(), 'short'),
          FALSE,
          NULL,
          'view',
          $this->languageManager->getCurrentLanguage()->getId(),
          $user->id(),
          $user->getDisplayName(),
          NULL,
          "100%",
          "100%"
      );

    $this->getLogger('onlyoffice')->debug('Generated config for media @type %label: <br><pre><code>' . print_r($editorConfig, TRUE) . '</code></pre>', $context);

    $build = [
      '#title' => $this->t('View Submission: @filename', ['@filename' => $file->getFilename()]),
      'page' => [
        '#theme' => 'onlyoffice_editor',
        '#config' => json_encode($editorConfig),
        '#filename' => $file->getFilename(),
        '#favicon_path' => '/' . $this->extensionListModule->getPath('onlyoffice') . '/images/' . $documentType . '.ico',
        '#doc_server_url' => $this->config('onlyoffice.settings')->get('doc_server_url') . OnlyofficeAppConfig::getDocServiceApiUrl(),
      ],
      '#attached' => [
        'library' => [
          'onlyoffice/editor',
        ],
      ],
    ];

    $html = $this->renderer->renderRoot($build);
    $response = new Response();
    $response->setContent($html);

    return $response;
  }
}
