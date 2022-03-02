<?php

namespace Drupal\onlyoffice_connector\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\onlyoffice_connector\OnlyofficeDocumentHelper;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Returns responses for ONLYOFFICE Connector routes.
 */
class OnlyofficeConnectorController extends ControllerBase
{

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * @var \Drupal\onlyoffice_connector\OnlyofficeDocumentHelper
   */
  protected $docHelper;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   */
  public function __construct(OnlyofficeDocumentHelper $docHelper, UserStorageInterface $userStorage, RendererInterface $renderer)
  {
    $this->docHelper = $docHelper;
    $this->renderer = $renderer;
    $this->userStorage = $userStorage;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('onlyoffice_connector.document_helper'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('renderer')
    );
  }

  public function edit(Media $media, Request $request)
  {
    if ($request->isMethod('get')) {
      return $this->editGet($media, $request);
    } else if ($request->isMethod('post')) {
      return $this->editPost($media, $request);
    } else {
      throw new BadRequestHttpException();
    }
  }

  public function editGet(Media $media, Request $request)
  {
    $build = [
      'page' => $this->getDocumentConfig($media)
    ];

    $build['page']['#theme'] = 'onlyoffice_editor';

    $html = $this->renderer->renderRoot($build);
    $response = new Response();
    $response->setContent($html);

    return $response;
  }

  public function editPost(Media $media, Request $request)
  {
    $errorMessage = NULL;

    $body = [];
    $content = $request->getContent();
    if (!empty($content)) {
      $body = json_decode($content, true);
    }

    // ToDo: check if null etc+

    $account = $this->userStorage->load($body["actions"][0]["userid"]);
    \Drupal::currentUser()->setAccount($account);

    $status = OnlyofficeConnectorController::CALLBACK_STATUS[$body["status"]];

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

    if ($errorMessage == NULL) {
      return new JsonResponse(['error' => 0], 200);
    } else {
      return new JsonResponse(['error' => 1, 'message' => $errorMessage], 400);
    }
  }

  private function proccess_save($body, Media $media)
  {
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

  private function getDocumentConfig(Media $media)
  {
    $fid = $media->toArray()["field_media_document"][0]["target_id"];
    $file = File::load($fid);

    $options = \Drupal::config('onlyoffice_connector.settings');

    $author = $file->getOwner();
    $user = \Drupal::currentUser()->getAccount();
    $filename = $file->getFilename();
    $extension = $this->docHelper->getExtension($filename);

    $can_edit = $this->docHelper->isEditable($extension) || $this->docHelper->isFillForms($extension);
    $edit_permission = $media->access("update", $user);

    $config = [
      'type' => 'desktop',
      'documentType' => $this->docHelper->getDocumentType($extension),
      'document' => [
        'title' => $filename,
        'url' => $file->createFileUrl(false),
        'fileType' => $extension,
        'key' => base64_encode($file->getChangedTime()),
        'info' => [
          'owner' => $author->getDisplayName(),
          'uploaded' => $file->getCreatedTime()
        ],
        'permissions' => [
          'download' => true,
          'edit' => $edit_permission
        ]
      ],
      'editorConfig' => [
        'mode' => $can_edit ? 'edit' : 'view',
        'lang' => 'en', // ToDo: change to user language
        'callbackUrl' => Url::fromRoute('onlyoffice_connector.editor', ['media' => $media->id()], ['absolute' => true])->toString(),
        'user' => [
          'id' => $user->id(),
          'name' => $user->getDisplayName()
        ]
      ]
    ];

    // ToDo: JWT

    return [
      '#config' => json_encode($config),
      '#filename' => $filename,
      '#doc_type' => $extension,
      '#doc_server_url' => $options->get('doc_server_url'),
      '#forms_unavailable_notice' => $this->t('forms_unavailable_notice')
    ];
  }

  const CALLBACK_STATUS = array(
    0 => 'NotFound',
    1 => 'Editing',
    2 => 'MustSave',
    3 => 'Corrupted',
    4 => 'Closed',
    6 => 'MustForceSave',
    7 => 'CorruptedForceSave'
  );
}
