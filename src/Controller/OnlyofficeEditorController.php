<?php

namespace Drupal\onlyoffice\Controller;

/**
 * Copyright (c) Ascensio System SIA 2026.
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

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Bytes;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\file\FileRepositoryInterface;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\onlyoffice\OnlyofficeAppConfig;
use Drupal\onlyoffice\OnlyofficeDocumentHelper;
use Drupal\onlyoffice\OnlyofficeDocumentService;
use Drupal\onlyoffice\OnlyofficeUrlHelper;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The document service.
   *
   * @var \Drupal\onlyoffice\OnlyofficeDocumentService
   */
  protected $documentService;

  /**
   * The file repository.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

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
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\onlyoffice\OnlyofficeDocumentService $document_service
   *   The document service.
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   The file repository.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    RendererInterface $renderer,
    OnlyofficeDocumentHelper $document_helper,
    DateFormatterInterface $date_formatter,
    LanguageManagerInterface $language_manager,
    ModuleExtensionList $extension_list_module,
    FileSystemInterface $file_system,
    OnlyofficeDocumentService $document_service,
    FileRepositoryInterface $file_repository,
    EventDispatcherInterface $event_dispatcher,
    TimeInterface $time,
  ) {
    $this->renderer = $renderer;
    $this->documentHelper = $document_helper;
    $this->dateFormatter = $date_formatter;
    $this->languageManager = $language_manager;
    $this->extensionListModule = $extension_list_module;
    $this->fileSystem = $file_system;
    $this->documentService = $document_service;
    $this->fileRepository = $file_repository;
    $this->eventDispatcher = $event_dispatcher;
    $this->time = $time;
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
          $container->get('extension.list.module'),
          $container->get('file_system'),
          $container->get('onlyoffice.document_service'),
          $container->get('file.repository'),
          $container->get('event_dispatcher'),
          $container->get('datetime.time')
      );
  }

  /**
   * Method for processing opening editor.
   */
  public function editor(Media $media, Request $request) {
    $pluginId = $media->getSource()->getPluginId();

    if (
      $pluginId != "file"
      && $pluginId != "onlyoffice_pdf_form"
    ) {
      throw new UnsupportedMediaTypeHttpException();
    }

    $editorType = 'desktop';

    if (preg_match(OnlyofficeAppConfig::USER_AGENT_MOBILE, $request->headers->get('User-Agent'))) {
      $editorType = 'mobile';
    }

    $mode = $request->query->get('mode', 'edit');

    $build = [
      'page' => $this->getDocumentConfig($editorType, $media, $mode),
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
  private function getDocumentConfig($editorType, Media $media, $mode) {
    $context = [
      '@type' => $media->bundle(),
      '%label' => $media->label(),
      'link' => OnlyofficeUrlHelper::getEditorLink($media)->toString(),
    ];

    $file = $media->get(OnlyofficeDocumentHelper::getSourceFieldName($media))->entity;
    $extension = OnlyofficeDocumentHelper::getExtension($file->getFilename());
    $documentType = OnlyofficeDocumentHelper::getDocumentType($extension);

    if (!$documentType) {
      $this->getLogger('onlyoffice')->warning('Media @type %label is not supported current module.', $context);
      return ['#error' => $this->t("Sorry, this file format isn't supported (@extension)", ['@extension' => $extension])];
    }

    $user = $this->currentUser()->getAccount();
    $can_edit = $this->documentHelper->isEditable($media);
    $edit_permission = $media->access("update", $user);

    $editorConfig = $this->documentHelper->createEditorConfig(
          $editorType,
          $this->documentHelper->getEditingKey($file),
          $file->getFilename(),
          OnlyofficeUrlHelper::getDownloadFileUrl($file),
          $media->getOwner()->getDisplayName(),
          $this->dateFormatter->format($media->getCreatedTime(), 'short'),
          $edit_permission,
          $edit_permission ? OnlyofficeUrlHelper::getCallbackUrl($media) : NULL,
          $edit_permission && $can_edit && $mode == 'edit' ? 'edit' : 'view',
          $this->languageManager->getCurrentLanguage()->getId(),
          $user->id(),
          $user->getDisplayName(),
          OnlyofficeUrlHelper::getGoBackUrl($media),
          "100%",
          "100%"
      );

    $this->getLogger('onlyoffice')->debug('Generated config for media @type %label: <br><pre><code>' . print_r($editorConfig, TRUE) . '</code></pre>', $context);

    $build = [
      '#config' => json_encode($editorConfig),
      '#filename' => $file->getFilename(),
      '#favicon_path' => '/' . $this->extensionListModule->getPath('onlyoffice') . '/images/' . $documentType . '.ico',
      '#doc_server_url' => $this->config('onlyoffice.settings')->get('doc_server_url') . OnlyofficeAppConfig::getDocServiceApiUrl(),
    ];

    if (
      $media->access('view', $user)
      && $this->entityTypeManager()->getAccessControlHandler('media')->createAccess($media->bundle(), $user)
    ) {
      $saveAsUrl = Url::fromRoute('onlyoffice.save_as', ['media' => $media->id()])->toString(TRUE);
      $build['#save_as_url'] = $saveAsUrl->getGeneratedUrl();
      BubbleableMetadata::createFromObject($saveAsUrl)->applyTo($build);
    }

    return $build;
  }

  /**
   * Saves the file from the Document Server URL as a new media of the bundle.
   */
  public function saveAs(Media $media, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    $data = is_array($data) ? $data : [];
    $title = is_string($data['title'] ?? NULL) ? basename($data['title']) : '';
    $fileType = is_string($data['fileType'] ?? NULL) ? strtolower($data['fileType']) : '';
    $url = is_string($data['url'] ?? NULL) ? $data['url'] : '';

    if ($title === '' || $url === '' || !preg_match('/^[a-z0-9]+$/', $fileType)) {
      return $this->error($this->t('Invalid request.'), 400);
    }

    if (!in_array($media->getSource()->getPluginId(), ['file', 'onlyoffice_pdf_form'], TRUE)) {
      return $this->error($this->t('Saving a copy is not supported for this media type.'), 415);
    }

    $context = [
      '@type' => $media->bundle(),
      '%label' => $media->label(),
      'link' => OnlyofficeUrlHelper::getEditorLink($media)->toString(),
    ];

    if (!$this->entityTypeManager()->getAccessControlHandler('media')->createAccess($media->bundle())) {
      $this->getLogger('onlyoffice')->error('Denied access to save a copy of media @type %label.', $context);
      return $this->error($this->t("You don't have permission to create media of this type."), 403);
    }

    if (!OnlyofficeUrlHelper::isDocumentServerUrl($url)) {
      $this->getLogger('onlyoffice')->error('The file URL @url is not under the ONLYOFFICE Docs address.', ['@url' => $url]);
      return $this->error($this->t('The file URL does not match the ONLYOFFICE Docs address.'), 400);
    }

    if (OnlyofficeDocumentHelper::getExtension($title) !== $fileType) {
      $title .= '.' . $fileType;
    }

    $sourceField = OnlyofficeDocumentHelper::getSourceFieldName($media);
    $fieldSettings = $media->get($sourceField)->getFieldDefinition()->getSettings();
    $maxFilesize = Bytes::toNumber((string) ($fieldSettings['max_filesize'] ?? ''));

    $event = new FileUploadSanitizeNameEvent($title, (string) ($fieldSettings['file_extensions'] ?? ''));
    $this->eventDispatcher->dispatch($event);
    $filename = $event->getFilename();

    $file = $media->get($sourceField)->entity;
    $directory = $this->fileSystem->dirname($file->getFileUri());
    $separator = substr($directory, -1) == '/' ? '' : '/';
    $destination = $directory . $separator . $filename;

    $candidate = $this->entityTypeManager()->getStorage('file')->create([
      'uri' => $destination,
      'filename' => $filename,
      'filesize' => 0,
      'uid' => $this->currentUser()->id(),
    ]);

    $copy = $media->getUntranslated()->createDuplicate();

    foreach (array_keys($copy->getTranslationLanguages(FALSE)) as $langcode) {
      $copy->removeTranslation($langcode);
    }

    $copy->set($sourceField, $candidate);
    $copy->setName($filename);
    $copy->setOwnerId($this->currentUser()->id());
    $copy->setUnpublished();
    $copy->setCreatedTime($this->time->getRequestTime());
    $copy->setRevisionUserId($this->currentUser()->id());
    $copy->setRevisionCreationTime($this->time->getRequestTime());
    $copy->setRevisionLogMessage('');

    foreach (['path', 'moderation_state'] as $field) {
      if ($copy->hasField($field)) {
        $copy->set($field, NULL);
      }
    }

    if ($violation = $this->getViolation($copy)) {
      return $this->error($violation, 400);
    }

    $size = 0;

    try {
      $content = $this->documentService->download($url, [
        'allow_redirects' => FALSE,
        'on_headers' => function (ResponseInterface $response) use (&$size, $maxFilesize) {
          if ($response->getStatusCode() >= 300) {
            throw new \RuntimeException('The file URL redirects.');
          }

          $size = (int) $response->getHeaderLine('Content-Length');

          if ($maxFilesize && $size > $maxFilesize) {
            throw new \RuntimeException('The file is larger than the maximum file size.');
          }
        },
      ]);
    }
    catch (\RuntimeException $e) {
      if ($maxFilesize && $size > $maxFilesize) {
        $candidate->setSize($size);
        return $this->error($this->getViolation($copy) ?? $this->t('Failed to save the copy.'), 400);
      }

      $content = '';
    }

    if ($content === '') {
      $this->getLogger('onlyoffice')->error('Error download file from @url.', ['@url' => $url]);
      return $this->error($this->t('Failed to download the copy from ONLYOFFICE Docs.'), 500);
    }

    $candidate->setSize(strlen($content));

    if ($violation = $this->getViolation($copy)) {
      return $this->error($violation, 400);
    }

    try {
      $newFile = $this->fileRepository->writeData($content, $destination);
    }
    catch (FileException | EntityStorageException $e) {
      $this->getLogger('onlyoffice')->error('Failed to write a copy of media @type %label: @message', $context + ['@message' => $e->getMessage()]);
      return $this->error($this->t('Failed to save the copy.'), 500);
    }

    $copy->set($sourceField, $newFile);

    try {
      $copy->save();
    }
    catch (EntityStorageException $e) {
      $newFile->delete();
      $this->getLogger('onlyoffice')->error('Failed to save a copy of media @type %label: @message', $context + ['@message' => $e->getMessage()]);
      return $this->error($this->t('Failed to save the copy.'), 500);
    }

    $this->getLogger('onlyoffice')->notice('A copy of media @type %label was saved as %name.', $context + ['%name' => $copy->label()]);

    return new JsonResponse([
      'error' => 0,
      'message' => PlainTextOutput::renderFromHtml((string) $this->t('The copy was saved as @name.', ['@name' => $copy->label()])),
    ]);
  }

  /**
   * Returns the first validation error of the copy.
   */
  private function getViolation(MediaInterface $copy): ?string {
    $violations = $copy->validate();

    return $violations->count() > 0 ? (string) $violations->get(0)->getMessage() : NULL;
  }

  /**
   * Returns the error response with the plain text message for the editor.
   */
  private function error(string|\Stringable $message, int $status): JsonResponse {
    return new JsonResponse(['error' => 1, 'message' => PlainTextOutput::renderFromHtml((string) $message)], $status);
  }

}
