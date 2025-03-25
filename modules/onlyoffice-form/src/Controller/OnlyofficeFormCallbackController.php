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

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\File\Exception\InvalidStreamWrapperException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\onlyoffice\OnlyofficeAppConfig;
use Drupal\onlyoffice\OnlyofficeDocumentHelper;
use Drupal\onlyoffice\OnlyofficeUrlHelper;
use Drupal\user\Entity\User;
use Drupal\user\UserStorageInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for ONLYOFFICE Connector routes.
 */
class OnlyofficeFormCallbackController extends ControllerBase {

  /**
   * The user account for the current operation.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * Defines the status of the document from document editing service.
   */
  const CALLBACK_STATUS = [
    0 => 'NotFound',
    1 => 'Editing',
    6 => 'MustForceSave',
    7 => 'CorruptedForceSave',
  ];

  /**
   * The user storage.
   *
   * @var \Drupal\user\Entity\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempstoreFactory;

  /**
   * Constructs a OnlyofficeCallbackController object.
   *
   * @param \Drupal\user\Entity\UserStorageInterface $user_storage
   *   The user storage.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $tempstore_factory
   *   The tempstore factory.
   */
  public function __construct(
    UserStorageInterface $user_storage,
    EntityRepositoryInterface $entity_repository,
    FileSystemInterface $file_system,
    StreamWrapperManagerInterface $streamWrapperManager,
    TimeInterface $time,
    SharedTempStoreFactory $tempstore_factory,
  ) {
    $this->userStorage = $user_storage;
    $this->entityRepository = $entity_repository;
    $this->fileSystem = $file_system;
    $this->streamWrapperManager = $streamWrapperManager;
    $this->time = $time;
    $this->tempstoreFactory = $tempstore_factory;
    $this->account = $this->currentUser();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('entity_type.manager')->getStorage('user'),
          $container->get('entity.repository'),
          $container->get('file_system'),
          $container->get('stream_wrapper_manager'),
          $container->get('datetime.time'),
          $container->get('tempstore.shared')
      );
  }

  /**
   * Method for processing callback.
   *
   * @param string $key
   *   The signed key.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function callback(string $key, Request $request) {

    $body = json_decode($request->getContent());
    $this->getLogger('onlyoffice')->debug('Request from Document Editing Service: <br><pre><code>' . print_r($body, TRUE) . '</code></pre>');

    if (!$body) {
      $this->getLogger('onlyoffice')->error('The request body is missing.');
      return new JsonResponse(
        ['error' => 1, 'message' => 'The request body is missing.'],
        400
      );
    }

    if ($this->config('onlyoffice.settings')->get('doc_server_jwt')) {
      $token = $body->token;
      $inBody = TRUE;

      if (empty($token)) {
        $jwtHeader = OnlyofficeAppConfig::getJwtHeader();
        $header = $request->headers->get($jwtHeader);
        $token = $header !== NULL ? substr($header, strlen("Bearer ")) : $header;
        $inBody = FALSE;
      }

      if (empty($token)) {
        $this->getLogger('onlyoffice')->error('The request token is missing.');
        return new JsonResponse(
          ['error' => 1, 'message' => 'The request token is missing.'],
          401
        );
      }

      try {
        $bodyFromToken = JWT::decode($token, new Key($this->config('onlyoffice.settings')->get('doc_server_jwt'), 'HS256'));

        $body = $inBody ? $bodyFromToken : $bodyFromToken->payload;
      }
      catch (\Exception $e) {
        $this->getLogger('onlyoffice')->error('Invalid request token.');
        return new JsonResponse(
          ['error' => 1, 'message' => 'Invalid request token.'],
          401
        );
      }
    }

    $linkParameters = OnlyofficeUrlHelper::verifyLinkKey($key);

    if (!$linkParameters) {
      $this->getLogger('onlyoffice')->error('Invalid link key: @key.', ['@key' => $key]);
      return new JsonResponse(
        ['error' => 1, 'message' => 'Invalid link key: ' . $key . '.'],
        400
      );
    }

    $uuid = $linkParameters[0];

    if (!$uuid || !Uuid::isValid($uuid)) {
      $this->getLogger('onlyoffice')->error('Invalid parameter UUID: @uuid.', ['@uuid' => $uuid]);
      return new JsonResponse(
        ['error' => 1, 'message' => 'Invalid parameter UUID: ' . $uuid . '.'],
        400
      );
    }

    $media = $this->entityRepository->loadEntityByUuid('media', $uuid);

    if (!$media) {
      $this->getLogger('onlyoffice')->error('The targeted media resource with UUID @uuid does not exist.', ['@uuid' => $uuid]);
      return new JsonResponse(
        [
          'error' => 1,
          'message' => 'The targeted media resource with UUID ' . $uuid . ' does not exist.',
        ],
        404
      );
    }

    $context = [
      '@type' => $media->bundle(),
      '%label' => $media->label(),
      'link' => OnlyofficeUrlHelper::getEditorLink($media)->toString(),
    ];

    $userId = isset($body->actions) ? $body->actions[0]->userid : NULL;

    $account = $this->userStorage->load($userId);

    // We can't directly set the account on the current user service
    // Instead, we'll use the account for operations but keep track of it.
    if (!$account) {
      $account = User::getAnonymousUser();
    }

    // Store the account for later use.
    $this->account = $account;

    $status = self::CALLBACK_STATUS[$body->status];

    switch ($status) {
      case "Editing":
        switch ($body->actions[0]->type) {
          case 0:
            $this->getLogger('onlyoffice')->notice('Disconnected from the media @type %label co-editing.', $context);
            break;

          case 1:
            $this->getLogger('onlyoffice')->notice('Connected to the media @type %label co-editing.', $context);
            break;
        }
        return new JsonResponse(['error' => 0], 200);

      case "MustForceSave":
      case "CorruptedForceSave":
        return $this->proccessForceSave($body, $media, $context);
    }

    return new JsonResponse(['error' => 1, 'message' => 'Unknown status: ' . $body->status], 400);
  }

  /**
   * Method for force saving file.
   */
  private function proccessForceSave($body, Media $media, $context) {
    $isSubmitForm = $body->forcesavetype === 3;

    $download_url = $body->url;
    if ($download_url === NULL) {
      $this->getLogger('onlyoffice')->error('URL parameter not found when saving media @type %label.', $context);
      return new JsonResponse(
        ['error' => 1, 'message' => 'Url parameter not found'],
        400
      );
    }

    // Get the original file from the media entity.
    $file = $media->get(OnlyofficeDocumentHelper::getSourceFieldName($media))->entity;
    $file_extension = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
    $media_name = $media->label();

    if ($new_data = file_get_contents($download_url)) {
      if ($isSubmitForm) {
        // Create a dedicated directory for this media's submissions.
        $media_uuid = $media->uuid();
        $submission_directory = 'public://onlyoffice_forms/submissions/' . $media_uuid;
        $this->fileSystem->prepareDirectory($submission_directory, FileSystemInterface::CREATE_DIRECTORY);

        // Generate a unique filename that includes the form name.
        $timestamp = $this->time->getRequestTime();
        $unique_id = substr(hash('sha256', $timestamp . rand()), 0, 8);
        $sanitized_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $media_name);
        $new_filename = $sanitized_name . '_submission_' . $unique_id . '.' . $file_extension;

        // Set the destination for the new file.
        $newDestination = $submission_directory . '/' . $new_filename;

        // Save the submitted form as a new file.
        $newFile = $this->writeData($new_data, $newDestination);

        // Create a form submission entity.
        $submission = $this->entityTypeManager()->getStorage('onlyoffice_form_submission')->create([
          'media_id' => $media->id(),
          'file_id' => $newFile->id(),
        // This will be 0 for anonymous users, which is correct.
          'uid' => $this->account->id(),
        ]);
        $submission->save();

        // For anonymous users, also store the submission in the session.
        if ($this->account->isAnonymous()) {
          // Use Drupal's shared tempstore for cross-session persistence.
          $tempstore = $this->tempstoreFactory->get('onlyoffice_form');

          // Store with a unique key that includes the media ID.
          $key = 'submission_' . $media->id();

          // Set with a longer expiration (default is 1 week)
          $tempstore->set($key, TRUE);

          $this->getLogger('onlyoffice_form')->debug('Stored submission in shared tempstore with key: @key', ['@key' => $key]);
        }

        $this->getLogger('onlyoffice_form')->notice('Form submission for media @type %label was successfully saved.', $context);
        return new JsonResponse(['error' => 0], 200);
      }
    }
    else {
      $this->getLogger('onlyoffice')->error('Error download file from @url.', ['@url' => $download_url]);
      return new JsonResponse(
        ['error' => 1, 'message' => 'Error download file from ' . $download_url],
        400
      );
    }
  }

  /**
   * Writing data to file.
   */
  private function writeData(string $data, string $destination, int $replace = FileSystemInterface::EXISTS_RENAME): FileInterface {
    if (!$this->streamWrapperManager->isValidUri($destination)) {
      throw new InvalidStreamWrapperException(sprintf('Invalid stream wrapper: %destination', ['%destination' => $destination]));
    }
    $uri = $this->fileSystem->saveData($data, $destination, $replace);

    $file = File::create(['uri' => $uri]);
    $file->setOwnerId($this->currentUser()->getAccount()->id());

    if ($replace === FileSystemInterface::EXISTS_RENAME && is_file($destination)) {
      $file->setFilename($this->fileSystem->basename($destination));
    }

    $file->setPermanent();
    $file->setSize(strlen($data));
    $file->save();

    return $file;
  }

}
