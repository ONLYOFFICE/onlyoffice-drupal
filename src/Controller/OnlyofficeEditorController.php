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
    $extension = OnlyofficeDocumentHelper::getExtension($file->getFilename());
    $documentType = OnlyofficeDocumentHelper::getDocumentType($extension);

    if (!$documentType) {
      return ['#error' => $this->t("Sorry, this file format isn't supported (@extension)", ['@extension' => $extension])];
    }

    $user = \Drupal::currentUser()->getAccount();
    $can_edit = $this->documentHelper->isEditable($extension) || $this->documentHelper->isFillForms($extension);
    $edit_permission = $media->access("update", $user);
    $callbackUrl = Url::fromRoute('onlyoffice_connector.callback', ['uuid' => $media->uuid()], ['absolute' => true])->toString();

    $editorConfig = $this->documentHelper->createEditorConfig(
      'desktop',
      $this->documentHelper->getEditingKey($file),
      $file->getFilename(),
      Url::fromRoute('onlyoffice_connector.download', ['uuid' => $file->uuid()], ['absolute' => true])->toString(),
      document_info_owner: $media->getOwner()->getDisplayName(),
      document_info_uploaded:$media->getCreatedTime(),
      document_permissions_edit: $edit_permission,
      editorConfig_callbackUrl: $edit_permission ? $callbackUrl : null,
      editorConfig_mode: $edit_permission && $can_edit ? 'edit' : 'view',
      editorConfig_lang: \Drupal::languageManager()->getCurrentLanguage()->getId(),
      editorConfig_user_id: $user->id(),
      editorConfig_user_name: $user->getDisplayName()
    );

    $options = \Drupal::config('onlyoffice_connector.settings');

    return [
      '#config' => json_encode($editorConfig),
      '#filename' => $file->getFilename(),
      '#doc_type' => $documentType,
      '#doc_server_url' => $options->get('doc_server_url'),
    ];
  }
}
