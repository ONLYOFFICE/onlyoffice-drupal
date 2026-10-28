<?php

namespace Drupal\onlyoffice\Hook;

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

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\onlyoffice\OnlyofficeAppConfig;
use Drupal\onlyoffice\OnlyofficeDocumentHelper;
use Drupal\onlyoffice\OnlyofficeUrlHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for the ONLYOFFICE Connector module.
 */
class OnlyofficeHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new OnlyofficeHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Psr\Log\LoggerInterface $logger
   *   The onlyoffice logger channel.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'logger.channel.onlyoffice')]
    protected readonly LoggerInterface $logger,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ModuleExtensionList $moduleExtensionList,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.onlyoffice':
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('The ONLYOFFICE connector allows users to edit office documents, spreadsheets, and presentations in the Media module from Drupal using ONLYOFFICE Docs packaged as Document Server. Users are able to collaborate on documents in real-time and preview files on public pages.') . '</p>';

        $output .= '<h3>' . $this->t('Installation and configuration') . '</h3>';
        $output .= '<p>' . $this->t('Explore how to install and configure the ONLYOFFICE module for Drupal on the <a href=":onlyoffice-drupal">official project page</a>.', [':onlyoffice-drupal' => 'https://github.com/ONLYOFFICE/onlyoffice-drupal']) . '</p>';

        return $output;
    }

    return NULL;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(array $existing, string $type, string $theme, string $path): array {
    return [
      'onlyoffice_editor' => [
        'variables' => [
          'filename' => 'noname',
          'favicon_path' => 'none',
          'doc_server_url' => 'http://127.0.0.1/',
          'config' => '{}',
          'error' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {
    try {
      if ($entity->getEntityTypeId() !== 'media') {
        return [];
      }

      if (!method_exists($entity, 'getSource')) {
        return [];
      }

      // Reload the entity to ensure it's fully loaded.
      try {
        /** @var \Drupal\media\Entity\Media $media */
        $media = $this->entityTypeManager
          ->getStorage('media')
          ->load($entity->id());

        if (!$media) {
          return [];
        }
      }
      catch (\Exception $e) {
        $this->logger->error('Failed to load media entity: @message', ['@message' => $e->getMessage()]);
        return [];
      }

      try {
        $source = $media->getSource();
        if (!$source) {
          return [];
        }

        $pluginId = $source->getPluginId();
        if (!in_array($pluginId, ['file', 'onlyoffice_pdf_form'])) {
          return [];
        }
      }
      catch (\Exception $e) {
        $this->logger->error('Error accessing media source: @message', ['@message' => $e->getMessage()]);
        return [];
      }

      $account = $this->currentUser->getAccount();
      if (!$media->access('view', $account)) {
        return [];
      }

      try {
        $source_field_name = OnlyofficeDocumentHelper::getSourceFieldName($media);

        if (!$media->hasField($source_field_name) || $media->get($source_field_name)->isEmpty()) {
          return [];
        }

        $file = $media->get($source_field_name)->entity;

        if (!$file) {
          return [];
        }

        $extension = OnlyofficeDocumentHelper::getExtension($file->getFilename());

        if (OnlyofficeDocumentHelper::getDocumentType($extension) === NULL) {
          return [];
        }

        $title = $this->t('View in ONLYOFFICE');

        if (OnlyofficeDocumentHelper::isEditable($media) && $media->access('update', $account)) {
          $title = $this->t('Edit in ONLYOFFICE');
        }

        return [
          'onlyoffice' => [
            'title' => $title,
            'weight' => 50,
            'url' => OnlyofficeUrlHelper::getEditorUrl($media),
          ],
        ];
      }
      catch (\Exception $e) {
        $this->logger->error('Error in onlyoffice_entity_operation: @message', ['@message' => $e->getMessage()]);
        return [];
      }
    }
    catch (\Throwable $t) {
      // Catch absolutely any error to prevent breaking the page.
      $this->logger->error('Unhandled error in onlyoffice_entity_operation: @message', ['@message' => $t->getMessage()]);
      return [];
    }
  }

  /**
   * Implements hook_locale_translation_projects_alter().
   */
  #[Hook('locale_translation_projects_alter')]
  public function localeTranslationProjectsAlter(array &$projects): void {
    if (!isset($projects['onlyoffice'])) {
      return;
    }

    $path = $this->moduleExtensionList->getPath('onlyoffice');
    $projects['onlyoffice']['info']['interface translation server pattern'] = $path . '/translations/onlyoffice.%language.po';
  }

  /**
   * Implements hook_library_info_build().
   */
  #[Hook('library_info_build')]
  public function libraryInfoBuild(): array {

    $options = $this->configFactory->get('onlyoffice.settings');

    if ($doc_server_url = $options->get('doc_server_url')) {
      $api_url = $doc_server_url . OnlyofficeAppConfig::getDocServiceApiUrl();
      return [
        'onlyoffice.api' => [
          'js' => [
            $api_url => [],
          ],
        ],
      ];
    }

    return [];
  }

}
