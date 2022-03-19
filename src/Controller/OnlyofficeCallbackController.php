<?php

namespace Drupal\onlyoffice_connector\Controller;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\Exception\InvalidStreamWrapperException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\onlyoffice_connector\OnlyofficeUrlHelper;
use Drupal\user\UserStorageInterface;
use Drupal\media\Entity\Media;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Firebase\JWT\JWT;
use Drupal\onlyoffice_connector\OnlyofficeAppConfig;
use Drupal\onlyoffice_connector\OnlyofficeDocumentHelper;

/**
 * Returns responses for ONLYOFFICE Connector routes.
 */
class OnlyofficeCallbackController extends ControllerBase {

  /**
   * Defines the status of the document from document editing service.
   */
  const CALLBACK_STATUS = array(
    0 => 'NotFound',
    1 => 'Editing',
    2 => 'MustSave',
    3 => 'Corrupted',
    4 => 'Closed',
    6 => 'MustForceSave',
    7 => 'CorruptedForceSave'
  );

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
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
   *
   * @param \Drupal\user\UserStorageInterface $user_storage
   * The user storage.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   * The entity repository.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   * The file system service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   * The stream wrapper manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   * The time service.
   */
  public function __construct(UserStorageInterface $user_storage, EntityRepositoryInterface $entity_repository,
                              FileSystemInterface $file_system, StreamWrapperManagerInterface $streamWrapperManager, TimeInterface $time) {
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

  public function callback($key, Request $request) {

    $body = json_decode($request->getContent());

    if (!$body) {
        throw new BadRequestHttpException("The request body is missing.");
    }

    if (\Drupal::config('onlyoffice_connector.settings')->get('doc_server_jwt')) {
      $token = $body->token;
      $inBody = true;

      if (empty($token)) {
        $jwtHeader = OnlyofficeAppConfig::getJwtHeader();
        $header = $request->headers->get($jwtHeader);
        $token = $header !== NULL ? substr($header, strlen("Bearer ")) : $header;
        $inBody = false;
      }

      if (empty($token)) {
        throw new UnauthorizedHttpException("Try save without JWT");
      }

      try {
        $bodyFromToken = JWT::decode($token, \Drupal::config('onlyoffice_connector.settings')->get('doc_server_jwt'), array("HS256"));

        $body = $inBody ? $bodyFromToken : $bodyFromToken->payload;
      } catch (\Exception $e) {
        throw new UnauthorizedHttpException("Try save with wrong JWT");
      }
    }

    $linkParameters = OnlyofficeUrlHelper::verifyLinkKey($key);

    if(!$linkParameters) {
      throw new BadRequestHttpException('Invalid link key.');
    }

    $uuid = $linkParameters[0];

    if (!$uuid || !Uuid::isValid($uuid)) {
      throw new BadRequestHttpException("Invalid parameter UUID.");
    }

    $media = $this->entityRepository->loadEntityByUuid('media', $uuid);

    if (!$media) {
      throw new BadRequestHttpException("The targeted media resource with UUID `{$uuid}` does not exist.");
    }

    $userId = isset($body->actions) ? $body->actions[0]->userid : null;

    $account = $this->userStorage->load($userId);
    \Drupal::currentUser()->setAccount($account);

    $status = OnlyofficeCallbackController::CALLBACK_STATUS[$body->status];

    switch ($status) {
      case "Editing":
        // ToDo: check if locking mechanisms exist
        break;
      case "MustSave":
      case "Corrupted":
        return $this->proccess_save($body, $media);
      case "MustForceSave":
      case "CorruptedForceSave":
        break;
    }

    return new JsonResponse(['error' => 0], 200);
  }

  private function proccess_save($body, Media $media) {
    $edit_permission = $media->access("update", \Drupal::currentUser()->getAccount());

    if (!$edit_permission) {
      return new JsonResponse(['error' => 1, 'message' => 'User does not have edit access to this media.'], 403);
    }

    $download_url = $body->url;
    if ($download_url === null) {
      return new JsonResponse(['error' => 1, 'message' => 'Url not found'], 400);
    }

    $file = $media->get(OnlyofficeDocumentHelper::getSourceFieldName($media))->entity;

    $directory = $this->fileSystem->dirname($file->getFileUri());
    $separator =  substr($directory, -1) == '/' ? '' : '/';
    $newDestination = $directory . $separator . $file->getFilename();
    $new_data = file_get_contents($download_url);

    $newFile = $this->writeData($new_data, $newDestination);

    $media->set(OnlyofficeDocumentHelper::getSourceFieldName($media), $newFile);
    $media->setNewRevision();
    $media->setRevisionUser(\Drupal::currentUser()->getAccount());
    $media->setRevisionCreationTime($this->time->getRequestTime());
    $media->setRevisionLogMessage('');
    $media->save();

    return new JsonResponse(['error' => 0], 200);
  }

  private function writeData(string $data, string $destination, int $replace = FileSystemInterface::EXISTS_RENAME): FileInterface {
    if (!$this->streamWrapperManager->isValidUri($destination)) {
      throw new InvalidStreamWrapperException(sprintf('Invalid stream wrapper: %destination', ['%destination' => $destination]));
    }
    $uri = $this->fileSystem->saveData($data, $destination, $replace);

    $file = File::create(['uri' => $uri]);
    $file->setOwnerId(\Drupal::currentUser()->getAccount()->id());

    if ($replace === FileSystemInterface::EXISTS_RENAME && is_file($destination)) {
      $file->setFilename($this->fileSystem->basename($destination));
    }

    $file->setPermanent();
    $file->setSize(strlen($data));
    $file->save();

    return $file;
  }

}
