<?php

namespace Drupal\onlyoffice\Controller;

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

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\onlyoffice\OnlyofficeAppConfig;
use Drupal\onlyoffice\OnlyofficeUrlHelper;
use Drupal\user\UserStorageInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for ONLYOFFICE Connector routes.
 */
class OnlyofficeDownloadController extends ControllerBase {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Constructs a OnlyofficeCallbackController object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    UserStorageInterface $user_storage,
  ) {
    $this->entityRepository = $entity_repository;
    $this->userStorage = $user_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('entity.repository'),
          $container->get('entity_type.manager')->getStorage('user')
      );
  }

  /**
   * Method for processing download.
   */
  public function download($key, Request $request) {

    if ($this->config('onlyoffice.settings')->get('doc_server_jwt')) {
      $jwtHeader = OnlyofficeAppConfig::getJwtHeader();
      $header = $request->headers->get($jwtHeader);
      $token = $header !== NULL ? substr($header, strlen("Bearer ")) : $header;

      if (empty($token)) {
        $this->getLogger('onlyoffice')->error('The request token is missing.');
        return new JsonResponse(
          ['error' => 1, 'message' => 'The request token is missing.'],
          401
        );
      }

      try {
        JWT::decode($token, new Key($this->config('onlyoffice.settings')->get('doc_server_jwt'), 'HS256'));
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
    $userId = $linkParameters[1];

    $account = $this->userStorage->load($userId);
    $this->currentUser()->setAccount($account);

    if (!$uuid || !Uuid::isValid($uuid)) {
      $this->getLogger('onlyoffice')->error('Invalid parameter UUID: @uuid.', ['@uuid' => $uuid]);
      return new JsonResponse(
        ['error' => 1, 'message' => 'Invalid parameter UUID: ' . $uuid . '.'],
        400
      );
    }
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityRepository->loadEntityByUuid('file', $uuid);

    if (!$file) {
      $this->getLogger('onlyoffice')->error('The targeted resource with UUID @uuid does not exist.', ['@uuid' => $uuid]);
      return new JsonResponse(
        [
          'error' => 1,
          'message' => 'The targeted resource with UUID ' . $uuid . ' does not exist.',
        ],
        404
      );
    }

    if (!$file->access('download')) {
      $this->getLogger('onlyoffice')->error(
        'Denied access to view @type %label.',
        ['@type' => $file->bundle(), '%label' => $file->label()]
      );
      return new JsonResponse(
        [
          'error' => 1,
          'message' => 'Denied access to view' . $file->bundle() . ' ' . $file->label(),
        ],
        403
      );
    }

    return new BinaryFileResponse($file->getFileUri(), 200);
  }

}
