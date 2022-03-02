<?php

namespace Drupal\onlyoffice_connector\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\onlyoffice_connector\OnlyofficeDocumentHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Returns responses for ONLYOFFICE Connector routes.
 */
class OnlyofficeEditorController extends ControllerBase {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The onlyoffice document helper service.
   *
   * @var \Drupal\onlyoffice_connector\OnlyofficeDocumentHelper
   */
  protected $documentHelper;

  /**
   * Constructs an OnlyofficeEditorController object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   * The renderer service.
   * @param \Drupal\onlyoffice_connector\OnlyofficeDocumentHelper $document_helper
   * The onlyoffice document helper service.
   */
  public function __construct(RendererInterface $renderer, OnlyofficeDocumentHelper $document_helper) {
    $this->renderer = $renderer;
    $this->documentHelper = $document_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('onlyoffice_connector.document_helper'),
    );
  }

  public function editor(Media $media, Request $request) {
    $build = [
      'page' => $this->getDocumentConfig($media)
    ];

    $build['page']['#theme'] = 'onlyoffice_editor';

    $html = $this->renderer->renderRoot($build);
    $response = new Response();
    $response->setContent($html);

    return $response;
  }

  private function getDocumentConfig(Media $media) {
    $fid = $media->toArray()["field_media_document"][0]["target_id"];
    $file = File::load($fid);

    $options = \Drupal::config('onlyoffice_connector.settings');

    $author = $file->getOwner();
    $user = \Drupal::currentUser()->getAccount();
    $filename = $file->getFilename();
    $extension = $this->documentHelper->getExtension($filename);

    $can_edit = $this->documentHelper->isEditable($extension) || $this->documentHelper->isFillForms($extension);
    $edit_permission = $media->access("update", $user);

    $config = [
      'type' => 'desktop',
      'documentType' => $this->documentHelper->getDocumentType($extension),
      'document' => [
        'title' => $filename,
        'url' => Url::fromRoute('onlyoffice_connector.download', ['uuid' => $media->uuid()], ['absolute' => true])->toString(),
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
        'callbackUrl' => Url::fromRoute('onlyoffice_connector.callback', ['uuid' => $media->uuid()], ['absolute' => true])->toString(),
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
}
