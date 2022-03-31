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

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\media\Entity\Media;
use Drupal\onlyoffice_connector\OnlyofficeDocumentHelper;
use Drupal\onlyoffice_connector\OnlyofficeUrlHelper;
use Drupal\onlyoffice_connector\OnlyofficeAppConfig;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

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
    $this->logger = $this->getLogger('onlyoffice_connector');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('onlyoffice_connector.document_helper')
    );
  }

  public function editor(Media $media, Request $request) {
    if ($media->getSource()->getPluginId() != "file") {
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
    $context = ['@type' => $media->bundle(), '%label' => $media->label(), 'link' => OnlyofficeUrlHelper::getEditorLink($media)->toString()];

    $file = $media->get(OnlyofficeDocumentHelper::getSourceFieldName($media))->entity;
    $extension = OnlyofficeDocumentHelper::getExtension($file->getFilename());
    $documentType = OnlyofficeDocumentHelper::getDocumentType($extension);

    if (!$documentType) {
      $this->logger->warning('Media @type %label is not supported current module.', $context);
      return ['#error' => $this->t("Sorry, this file format isn't supported (@extension)", ['@extension' => $extension])];
    }

    $user = \Drupal::currentUser()->getAccount();
    $can_edit = $this->documentHelper->isEditable($media) || $this->documentHelper->isFillForms($media);
    $edit_permission = $media->access("update", $user);

    $editorConfig = $this->documentHelper->createEditorConfig(
      'desktop',
      $this->documentHelper->getEditingKey($file),
      $file->getFilename(),
      OnlyofficeUrlHelper::getDownloadFileUrl($file),
      document_info_owner: $media->getOwner()->getDisplayName(),
      document_info_uploaded: \Drupal::service('date.formatter')->format($media->getCreatedTime(), 'short'),
      document_permissions_edit: $edit_permission,
      editorConfig_callbackUrl: $edit_permission ? OnlyofficeUrlHelper::getCallbackUrl($media) : null,
      editorConfig_mode: $edit_permission && $can_edit ? 'edit' : 'view',
      editorConfig_lang: \Drupal::languageManager()->getCurrentLanguage()->getId(),
      editorConfig_user_id: $user->id(),
      editorConfig_user_name: $user->getDisplayName(),
      editorConfig_customization_goback_url: OnlyofficeUrlHelper::getGoBackUrl($media)
    );

    $this->logger->debug('Generated config for media @type %label: <br><pre><code>' . print_r($editorConfig, TRUE) . '</code></pre>', $context);

    $options = \Drupal::config('onlyoffice_connector.settings');

    return [
      '#config' => json_encode($editorConfig),
      '#filename' => $file->getFilename(),
      '#doc_type' => $documentType,
      '#doc_server_url' => $options->get('doc_server_url') . OnlyofficeAppConfig::getDocServiceApiUrl(),
    ];
  }
}
