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

namespace Drupal\onlyoffice_connector\Controller;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\onlyoffice_connector\OnlyofficeAppConfig;
use Drupal\onlyoffice_connector\OnlyofficeUrlHelper;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Firebase\JWT\JWT;

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
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   * The entity repository.
   * @param \Drupal\user\UserStorageInterface $user_storage
   * The user storage.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, UserStorageInterface $user_storage) {
    $this->entityRepository = $entity_repository;
    $this->userStorage = $user_storage;
    $this->logger = $this->getLogger('onlyoffice_connector');
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

  public function download($key, Request $request) {

    if (\Drupal::config('onlyoffice_connector.settings')->get('doc_server_jwt')) {
      $jwtHeader = OnlyofficeAppConfig::getJwtHeader();
      $header = $request->headers->get($jwtHeader);
      $token = $header !== NULL ?  substr($header, strlen("Bearer ")) : $header;

      if (empty($token)) {
        $this->logger->error('The request token is missing.');
        return new JsonResponse(['error' => 1, 'message' => 'The request token is missing.'], 401);
      }

      try {
        JWT::decode($token, \Drupal::config('onlyoffice_connector.settings')->get('doc_server_jwt'), array("HS256"));
      } catch (\Exception $e) {
        $this->logger->error('Invalid request token.');
        return new JsonResponse(['error' => 1, 'message' => 'Invalid request token.'], 401);
      }
    }

    $linkParameters = OnlyofficeUrlHelper::verifyLinkKey($key);

    if(!$linkParameters) {
      $this->logger->error('Invalid link key: @key.', [ '@key' => $key ]);
      return new JsonResponse(['error' => 1, 'message' => 'Invalid link key: ' . $key . '.'], 400);
    }

    $uuid = $linkParameters[0];
    $userId = $linkParameters[1];

    $account = $this->userStorage->load($userId);
    \Drupal::currentUser()->setAccount($account);

    if (!$uuid || !Uuid::isValid($uuid)) {
      $this->logger->error('Invalid parameter UUID: @uuid.', [ '@uuid' => $uuid ]);
      return new JsonResponse(['error' => 1, 'message' => 'Invalid parameter UUID: ' . $uuid . '.'], 400);
    }
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityRepository->loadEntityByUuid('file', $uuid);

    if (!$file) {
      $this->logger->error('The targeted resource with UUID @uuid does not exist.', [ '@uuid' => $uuid ]);
      return new JsonResponse(['error' => 1, 'message' => 'The targeted resource with UUID ' . $uuid . ' does not exist.'], 404);
    }

    if (!$file->access('download')) {
      $this->logger->error('Denied access to view @type %label.', ['@type' => $file->bundle(), '%label' => $file->label()]);
      return new JsonResponse(['error' => 1, 'message' => 'Denied access to view' . $file->bundle() . ' ' . $file->label() ], 403);
    }

    return new BinaryFileResponse($file->getFileUri(), 200);
  }

}
