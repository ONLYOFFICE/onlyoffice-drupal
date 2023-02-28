<?php

namespace Drupal\onlyoffice\Plugin\Field\FieldFormatter;

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

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Render\RendererInterface;
use Drupal\media\Entity\Media;
use Drupal\onlyoffice\OnlyofficeDocumentHelper;
use Drupal\onlyoffice\OnlyofficeUrlHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'onlyoffice_form' formatter.
 *
 * @FieldFormatter(
 *   id = "onlyoffice_form",
 *   label = @Translation("ONLYOFFICE Form"),
 *   description = @Translation("Display the file using ONLYOFFICE Editor."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class OnlyofficeFormFormatter extends OnlyofficeBaseFormatter {

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
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The page cache disabling policy.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $pageCacheKillSwitch;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  /**
   * Constructs an OnlyofficeFormFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $page_cache_kill_switch
   *   The page cache disabling policy.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   The UUID service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    DateFormatterInterface $date_formatter,
    LanguageManagerInterface $language_manager,
    RendererInterface $renderer,
    KillSwitch $page_cache_kill_switch,
    UuidInterface $uuid_service
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->dateFormatter = $date_formatter;
    $this->languageManager = $language_manager;
    $this->renderer = $renderer;
    $this->pageCacheKillSwitch = $page_cache_kill_switch;
    $this->uuidService = $uuid_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('date.formatter'),
      $container->get('language_manager'),
      $container->get('renderer'),
      $container->get('page_cache_kill_switch'),
      $container->get('uuid')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    if (!parent::isApplicable($field_definition)) {
      return FALSE;
    }

    if ($field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'media') {
      $handler_settings = $field_definition->getSetting('handler_settings');

      if (!empty($handler_settings['target_bundles']) && count($handler_settings['target_bundles']) == 1) {
        /** @var \Drupal\media\MediaTypeInterface $media_type */
        $media_type = \Drupal::entityTypeManager()->getStorage('media_type')->load(array_key_first($handler_settings['target_bundles']));
        return $media_type->getSource()->getPluginId() == 'onlyoffice_form';
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $this->pageCacheKillSwitch->trigger();

    $element = parent::viewElements($items, $langcode);

    /** @var \Drupal\media\Entity\Media $media */
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $media) {
      $editor_id = sprintf(
            '%s-%s-iframeOnlyofficeEditor',
            $media->getEntityTypeId(),
            $media->id()
      );

      $element[$delta] = [
        '#markup' => sprintf('<div id="%s" class="onlyoffice-editor"></div>', $editor_id),
        '#cache' => [
          'max-age' => 0,
        ],
      ];

      $element['#attached']['drupalSettings']['onlyofficeData'][$editor_id] = [
        'config' => $this->getEditorConfig($media),
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity) {
    $account = \Drupal::currentUser()->getAccount();
    return $entity->access('view', $account, TRUE);
  }

  /**
   * Method getting configuration for document editor service.
   */
  private function getEditorConfig(Media $media) {

    $file = $media->get(OnlyofficeDocumentHelper::getSourceFieldName($media))->entity;

    $account = \Drupal::currentUser()->getAccount();

    $editor_width = $this->getSetting('width') . $this->getSetting('width_unit');
    $editor_height = $this->getSetting('height') . $this->getSetting('height_unit');

    return OnlyofficeDocumentHelper::createEditorConfig(
      'desktop',
      $this->uuidService->generate(),
      $file->getFilename(),
      OnlyofficeUrlHelper::getDownloadFileUrl($file),
      $media->getOwner()->getDisplayName(),
      $this->dateFormatter->format($media->getCreatedTime(), 'short'),
      TRUE,
      TRUE,
      OnlyofficeUrlHelper::getCallbackFillFormUrl($media),
      'edit',
      $this->languageManager->getCurrentLanguage()->getId(),
      $account->id(),
      $account->getDisplayName(),
      NULL,
      TRUE,
      TRUE,
      TRUE,
      $editor_width,
      $editor_height
    );
  }

}
