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
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\onlyoffice_form\OnlyofficeFormDocumentHelper;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;

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
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\DatabaseFileUsageBackend
   */
  protected $fileUsage;

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
   * @param \Drupal\file\FileUsage\DatabaseFileUsageBackend $file_usage
   *   The file usage service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    FileSystemInterface $file_system,
    OnlyofficeFormDocumentHelper $document_helper,
    DatabaseFileUsageBackend $file_usage,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->fileSystem = $file_system;
    $this->documentHelper = $document_helper;
    $this->fileUsage = $file_usage;
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
      $container->get('file.usage'),
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
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('No file uploaded.'),
      ], 400);
    }

    $uploadedFile = $files['upload_file'];

    // Validate the file.
    if (!$uploadedFile->isValid()) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Invalid file upload.'),
      ], 400);
    }

    // Prepare the destination directory.
    $directory = 'public://onlyoffice_forms/';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    // Save the file.
    try {
      $file = File::create([
        'uri' => $uploadedFile->getRealPath(),
        'filename' => $uploadedFile->getClientOriginalName(),
        'filemime' => $uploadedFile->getMimeType(),
        'status' => File::STATUS_PERMANENT,
      ]);

      // Move the uploaded file to the destination.
      $destination = $directory . $uploadedFile->getClientOriginalName();
      $this->fileSystem->move($uploadedFile->getRealPath(), $destination);
      $file->setFileUri($destination);

      // Save the file entity.
      $file->save();

      // Set file usage to prevent it from being deleted during cron.
      $this->fileUsage->add($file, 'onlyoffice_form', 'onlyoffice_form_pdf', $file->id());

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
