<?php

namespace Drupal\onlyoffice_connector\Controller;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\user\UserStorageInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
   *
   * @param \Drupal\user\UserStorageInterface $user_storage
   * The user storage.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   * The entity repository.
   */
  public function __construct(UserStorageInterface $user_storage, EntityRepositoryInterface $entity_repository) {
    $this->userStorage = $user_storage;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('entity.repository')
    );
  }

  public function callback($uuid, Request $request) {
    if (!$uuid || !Uuid::isValid($uuid)) {
      throw new BadRequestHttpException();
    }

    $media = $this->entityRepository->loadEntityByUuid('media', $uuid);

    if (!$media) {
      throw new BadRequestHttpException("The targeted media resource with UUID `{$uuid}` does not exist.");
    }

    $body = [];
    $content = $request->getContent();
    if (!empty($content)) {
      $body = json_decode($content, true);
    }

    // ToDo: check if null etc+

    $account = $this->userStorage->load($body["actions"][0]["userid"]);
    \Drupal::currentUser()->setAccount($account);

    $status = OnlyofficeCallbackController::CALLBACK_STATUS[$body["status"]];
    $errorMessage = null;

    switch ($status) {
      case "Editing":
        // ToDo: check if locking mechanisms exist
        break;
      case "MustSave":
      case "Corrupted":
        $errorMessage = $this->proccess_save($body, $media);
        break;
      case "MustForceSave":
      case "CorruptedForceSave":
        break;
    }
    //https://api.drupal.org/api/drupal/core%21modules%21file%21file.module/function/file_save_data/8.8.x

    if ($errorMessage == null) {
      return new JsonResponse(['error' => 0], 200);
    } else {
      return new JsonResponse(['error' => 1, 'message' => $errorMessage], 400);
    }
  }

  private function proccess_save($body, Media $media) {
    $fid = $media->toArray()["field_media_document"][0]["target_id"];
    $file = File::load($fid);

    $download_url = $body["url"];
    if ($download_url === null) {
      return 'nothing to save';
    }

    $new_data = file_get_contents($download_url);
    if ($new_data === null) return 'nothing to save';

    $filepath = $file->getFileUri();
    $result = \Drupal::service('file_system')->saveData($new_data, $filepath, FileSystemInterface::EXISTS_REPLACE);

    if ($result === 0) return 'saving failed';
    $file->setSize(strlen($new_data));
    $file->save();

    return NULL;
  }
}
