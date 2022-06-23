<?php

namespace Drupal\onlyoffice\Controller;

/**
 * Copyright (c) Ascensio System SIA 2022.
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

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\media\Entity\Media;
use Drupal\onlyoffice\OnlyofficeDocumentHelper;
use Drupal\onlyoffice\OnlyofficeUrlHelper;
use Drupal\onlyoffice\OnlyofficeAppConfig;
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
   * @var \Drupal\onlyoffice\OnlyofficeDocumentHelper
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
   *   The renderer service.
   * @param \Drupal\onlyoffice\OnlyofficeDocumentHelper $document_helper
   *   The onlyoffice document helper service.
   */
  public function __construct(RendererInterface $renderer, OnlyofficeDocumentHelper $document_helper) {
    $this->renderer = $renderer;
    $this->documentHelper = $document_helper;
    $this->logger = $this->getLogger('onlyoffice');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('renderer'),
          $container->get('onlyoffice.document_helper')
      );
  }

  /**
   * Method for processing opening editor.
   */
  public function editor(Media $media, Request $request) {
    if ($media->getSource()->getPluginId() != "file") {
      throw new UnsupportedMediaTypeHttpException();
    }

    $editorType = 'desktop';

    if (preg_match(OnlyofficeAppConfig::USER_AGENT_MOBILE, $request->headers->get('User-Agent'))) {
      $editorType = 'mobile';
    }

    $build = [
      'page' => $this->getDocumentConfig($editorType, $media),
    ];

    $build['page']['#theme'] = 'onlyoffice_editor';

    $html = $this->renderer->renderRoot($build);
    $response = new Response();
    $response->setContent($html);

    return $response;
  }

  /**
   * Method for generating configuration for document editor service.
   */
  private function getDocumentConfig($editorType, Media $media) {
    $context = [
      '@type' => $media->bundle(),
      '%label' => $media->label(),
      'link' => OnlyofficeUrlHelper::getEditorLink($media)->toString(),
    ];

    $file = $media->get(OnlyofficeDocumentHelper::getSourceFieldName($media))->entity;
    $extension = OnlyofficeDocumentHelper::getExtension($file->getFilename());
    $documentType = OnlyofficeDocumentHelper::getDocumentType($extension);

    if (!$documentType) {
      $this->logger->warning('Media @type %label is not supported current module.', $context);
      return ['#error' => $this->t("Sorry, this file format isn't supported (@extension)", ['@extension' => $extension])];
    }

    $user = \Drupal::currentUser()->getAccount();
    $can_edit = $this->documentHelper->isEditable($media);
    $edit_permission = $media->access("update", $user);

    $editorConfig = $this->documentHelper->createEditorConfig(
          $editorType,
          $this->documentHelper->getEditingKey($file),
          $file->getFilename(),
          OnlyofficeUrlHelper::getDownloadFileUrl($file),
          document_info_owner: $media->getOwner()->getDisplayName(),
          document_info_uploaded: \Drupal::service('date.formatter')->format($media->getCreatedTime(), 'short'),
          document_permissions_edit: $edit_permission,
          editorConfig_callbackUrl: $edit_permission ? OnlyofficeUrlHelper::getCallbackUrl($media) : NULL,
          editorConfig_mode: $edit_permission && $can_edit ? 'edit' : 'view',
          editorConfig_lang: \Drupal::languageManager()->getCurrentLanguage()->getId(),
          editorConfig_user_id: $user->id(),
          editorConfig_user_name: $user->getDisplayName(),
          editorConfig_customization_goback_url: OnlyofficeUrlHelper::getGoBackUrl($media)
      );

    $this->logger->debug('Generated config for media @type %label: <br><pre><code>' . print_r($editorConfig, TRUE) . '</code></pre>', $context);

    $options = \Drupal::config('onlyoffice.settings');

    return [
      '#config' => json_encode($editorConfig),
      '#filename' => $file->getFilename(),
      '#favicon_path' => '/' . \Drupal::service('extension.list.module')->getPath('onlyoffice') . '/images/' . $documentType . '.ico',
      '#doc_server_url' => $options->get('doc_server_url') . OnlyofficeAppConfig::getDocServiceApiUrl(),
    ];
  }

}
