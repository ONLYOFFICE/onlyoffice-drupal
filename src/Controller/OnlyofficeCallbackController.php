<?php

namespace Drupal\onlyoffice\Controller;

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

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\Exception\InvalidStreamWrapperException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\onlyoffice\OnlyofficeUrlHelper;
use Drupal\user\Entity\User;
use Drupal\user\UserStorageInterface;
use Drupal\media\Entity\Media;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Drupal\onlyoffice\OnlyofficeAppConfig;
use Drupal\onlyoffice\OnlyofficeDocumentHelper;

/**
 * Returns responses for ONLYOFFICE Connector routes.
 */
class OnlyofficeCallbackController extends ControllerBase {

  /**
   * Defines the status of the document from document editing service.
   */
  const CALLBACK_STATUS = [
    0 => 'NotFound',
    1 => 'Editing',
    2 => 'MustSave',
    3 => 'Corrupted',
    4 => 'Closed',
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
   */
  public function __construct(
        UserStorageInterface $user_storage,
        EntityRepositoryInterface $entity_repository,
        FileSystemInterface $file_system,
        StreamWrapperManagerInterface $streamWrapperManager,
        TimeInterface $time
    ) {
    $this->userStorage = $user_storage;
    $this->entityRepository = $entity_repository;
    $this->fileSystem = $file_system;
    $this->streamWrapperManager = $streamWrapperManager;
    $this->time = $time;
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
          $container->get('datetime.time')
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

    if ($account) {
      $this->currentUser()->setAccount($account);
    }
    else {
      $this->currentUser()->setAccount(User::getAnonymousUser());
    }

    $status = OnlyofficeCallbackController::CALLBACK_STATUS[$body->status];

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
        break;

      case "MustSave":
      case "Corrupted":
        return $this->proccessSave($body, $media, $context);

      case "Closed":
        $this->getLogger('onlyoffice')->notice('Media @type %label was closed with no changes.', $context);
        break;

      case "MustForceSave":
      case "CorruptedForceSave":
        break;
    }

    return new JsonResponse(['error' => 0], 200);
  }

  /**
   * Method for saving file.
   */
  private function proccessSave($body, Media $media, $context) {
    $edit_permission = $media->access("update", $this->currentUser()->getAccount());

    if (!$edit_permission) {
      $this->getLogger('onlyoffice')->error('Denied access to edit media @type %label.', $context);
      return new JsonResponse(
        [
          'error' => 1,
          'message' => 'User does not have edit access to this media.',
        ],
        403
      );
    }

    $download_url = $body->url;
    if ($download_url === NULL) {
      $this->getLogger('onlyoffice')->error('URL parameter not found when saving media @type %label.', $context);
      return new JsonResponse(
        ['error' => 1, 'message' => 'Url parameter not found'],
        400
      );
    }

    $file = $media->get(OnlyofficeDocumentHelper::getSourceFieldName($media))->entity;

    $directory = $this->fileSystem->dirname($file->getFileUri());
    $separator = substr($directory, -1) == '/' ? '' : '/';
    $newDestination = $directory . $separator . $file->getFilename();
    $new_data = file_get_contents($download_url);

    $newFile = $this->writeData($new_data, $newDestination);

    $media->set(OnlyofficeDocumentHelper::getSourceFieldName($media), $newFile);
    $media->setNewRevision();
    $media->setRevisionUser($this->currentUser()->getAccount());
    $media->setRevisionCreationTime($this->time->getRequestTime());
    $media->setRevisionLogMessage('');
    $media->save();

    $this->getLogger('onlyoffice')->notice('Media @type %label was successfully saved.', $context);
    return new JsonResponse(['error' => 0], 200);
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
