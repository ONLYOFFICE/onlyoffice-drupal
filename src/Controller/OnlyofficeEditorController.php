<?php

namespace Drupal\onlyoffice_connector\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\media\Entity\Media;
use Drupal\onlyoffice_connector\OnlyofficeDocumentHelper;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Firebase\JWT\JWT;

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
    if ($media->getEntityTypeId() != "media" || $media->bundle() != "document") {
      throw new UnsupportedMediaTypeHttpException();
    }

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
    $file = $media->get(OnlyofficeDocumentHelper::getSourceFieldName($media))->entity;
    $filename = $file->getFilename();
    $extension = $this->documentHelper->getExtension($filename);
    $documentType = $this->documentHelper->getDocumentType($extension);

    if (!$documentType) {
      return ['#error' => $this->t("Sorry, this file format isn't supported (@extension)", ['@extension' => $extension])];
    }

    $options = \Drupal::config('onlyoffice_connector.settings');

    $author = $file->getOwner();
    $user = \Drupal::currentUser()->getAccount();

    $can_edit = $this->documentHelper->isEditable($extension) || $this->documentHelper->isFillForms($extension);
    $edit_permission = $media->access("update", $user);

    $config = [
      'type' => 'desktop',
      'width' => "100%",
      'height' => "100%",
      'documentType' => $documentType,
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

    if ($options->get('doc_server_jwt')) {
      $token = JWT::encode($config, $options->get('doc_server_jwt'));
      $config["token"] = $token;
    }

    return [
      '#config' => json_encode($config),
      '#filename' => $filename,
      '#doc_type' => $documentType,
      '#doc_server_url' => $options->get('doc_server_url'),
    ];
  }
}
