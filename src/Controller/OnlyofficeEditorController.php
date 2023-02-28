<?php

namespace Drupal\onlyoffice\Controller;

/**
 * Copyright (c) Ascensio System SIA 2023.
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
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
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
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The list of available modules.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $extensionListModule;

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
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
   *   The list of available modules.
   */
  public function __construct(
    RendererInterface $renderer,
    OnlyofficeDocumentHelper $document_helper,
    DateFormatterInterface $date_formatter,
    LanguageManagerInterface $language_manager,
    ModuleExtensionList $extension_list_module
  ) {
    $this->renderer = $renderer;
    $this->documentHelper = $document_helper;
    $this->dateFormatter = $date_formatter;
    $this->languageManager = $language_manager;
    $this->extensionListModule = $extension_list_module;
    $this->logger = $this->getLogger('onlyoffice');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('renderer'),
          $container->get('onlyoffice.document_helper'),
          $container->get('date.formatter'),
          $container->get('language_manager'),
          $container->get('extension.list.module')
      );
  }

  /**
   * Method for processing opening editor.
   */
  public function editor(Media $media, Request $request) {
    $pluginId = $media->getSource()->getPluginId();

    if ($pluginId != "file" && $pluginId != "onlyoffice_m_form" && $pluginId != "onlyoffice_form") {
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

    $user = $this->currentUser()->getAccount();
    $can_edit = $this->documentHelper->isEditable($media) || $this->documentHelper->isFillForms($media);
    $edit_permission = $media->access("update", $user);

    $editorConfig = $this->documentHelper->createEditorConfig(
          $editorType,
          $this->documentHelper->getEditingKey($file),
          $file->getFilename(),
          OnlyofficeUrlHelper::getDownloadFileUrl($file),
          $media->getOwner()->getDisplayName(),
          $this->dateFormatter->format($media->getCreatedTime(), 'short'),
          $edit_permission,
          TRUE,
          $edit_permission ? OnlyofficeUrlHelper::getCallbackUrl($media) : NULL,
          $edit_permission && $can_edit ? 'edit' : 'view',
          $this->languageManager->getCurrentLanguage()->getId(),
          $user->id(),
          $user->getDisplayName(),
          OnlyofficeUrlHelper::getGoBackUrl($media),
          FALSE,
          FALSE,
          FALSE,
          "100%",
          "100%"
      );

    $this->logger->debug('Generated config for media @type %label: <br><pre><code>' . print_r($editorConfig, TRUE) . '</code></pre>', $context);

    return [
      '#config' => json_encode($editorConfig),
      '#filename' => $file->getFilename(),
      '#favicon_path' => '/' . $this->extensionListModule->getPath('onlyoffice') . '/images/' . $documentType . '.ico',
      '#doc_server_url' => $this->config('onlyoffice.settings')->get('doc_server_url') . OnlyofficeAppConfig::getDocServiceApiUrl(),
    ];
  }

}
