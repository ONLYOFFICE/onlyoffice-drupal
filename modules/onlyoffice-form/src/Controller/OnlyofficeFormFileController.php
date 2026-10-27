<?php

namespace Drupal\onlyoffice_form\Controller;

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

use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Environment;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\onlyoffice_form\OnlyofficeFormDocumentHelper;
use Drupal\file\Upload\FileUploadHandler;
use Drupal\file\Upload\FormUploadedFile;

/**
 * Controller for handling file uploads.
 */
class OnlyofficeFormFileController extends ControllerBase {

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
   * The ONLYOFFICE form document helper.
   *
   * @var \Drupal\onlyoffice_form\OnlyofficeFormDocumentHelper
   */
  protected $documentHelper;

  /**
   * The file upload handler.
   *
   * @var \Drupal\file\Upload\FileUploadHandler
   */
  protected $fileUploadHandler;

  /**
   * Constructs a new OnlyofficeFormFileController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\onlyoffice_form\OnlyofficeFormDocumentHelper $document_helper
   *   The ONLYOFFICE form document helper.
   * @param \Drupal\file\Upload\FileUploadHandler $file_upload_handler
   *   The file upload handler.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    FileSystemInterface $file_system,
    OnlyofficeFormDocumentHelper $document_helper,
    FileUploadHandler $file_upload_handler,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->fileSystem = $file_system;
    $this->documentHelper = $document_helper;
    $this->fileUploadHandler = $file_upload_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('file_system'),
      $container->get('onlyoffice_form.document_helper'),
      $container->get('file.upload_handler'),
    );
  }

  /**
   * Handles file uploads.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function uploadFile(Request $request) {
    // Get the uploaded file.
    $files = $request->files->get('files');

    if (empty($files) || empty($files['upload_file'])) {
      // PHP drops the whole request body when it exceeds post_max_size.
      $post_max_size = Bytes::toNumber(ini_get('post_max_size'));
      if ($post_max_size > 0 && (int) $request->server->get('CONTENT_LENGTH') > $post_max_size) {
        return new JsonResponse([
          'status' => 'error',
          'message' => $this->t('An unrecoverable error occurred. The uploaded file likely exceeded the maximum file size (@size) that this server supports.', [
            '@size' => ByteSizeMarkup::create(Environment::getUploadMaxSize()),
          ]),
        ], 413);
      }

      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('No file uploaded.'),
      ], 400);
    }

    $uploadedFile = $files['upload_file'];

    // Prepare the destination directory.
    $directory = 'public://onlyoffice_forms/';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    // Save the file.
    try {
      $result = $this->fileUploadHandler->handleFileUpload(
        new FormUploadedFile($uploadedFile),
        ['FileExtension' => ['extensions' => 'pdf']],
        $directory,
        FileExists::Rename,
        // Drupal 10 throws on violations unless this is FALSE; 11+ ignores it.
        FALSE,
      );

      if ($result->hasViolations()) {
        return new JsonResponse([
          'status' => 'error',
          'message' => (string) $result->getViolations()->get(0)->getMessage(),
        ], 400);
      }

      $file = $result->getFile();

      if ($file->getFilename() !== $result->getSanitizedFilename()) {
        $file->setFilename($result->getSanitizedFilename());
        $file->save();
      }

      // Return success response with file ID.
      return new JsonResponse([
        'status' => 'success',
        'fid' => $file->id(),
        'filename' => $file->getFilename(),
      ]);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('onlyoffice_form')->error('Error uploading file: @error', ['@error' => $e->getMessage()]);

      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Failed to save the file. Please try again.'),
      ], 500);
    }
  }

}
