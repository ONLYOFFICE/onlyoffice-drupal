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

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\onlyoffice\OnlyofficeDocumentHelper;
use Drupal\onlyoffice\OnlyofficeUrlHelper;

/**
 * Plugin implementation of the 'file_document' formatter.
 *
 * @FieldFormatter(
 *   id = "onlyoffice_preview",
 *   label = @Translation("ONLYOFFICE Preview"),
 *   description = @Translation("Displaying files using the ONLYOFFICE editor."),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class OnlyofficePreviewFormatter extends FileFormatterBase {

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
   * Construct the OnlyofficePreviewFormatter.
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
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->dateFormatter = $date_formatter;
    $this->languageManager = $language_manager;
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
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'width_unit' => '%',
      'width' => 100,
      'height_unit' => 'px',
      'height' => 640,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return parent::settingsForm($form, $form_state) + [
      'width_unit' => [
        '#type' => 'radios',
        '#title' => $this->t('Width units'),
        '#default_value' => $this->getSetting('width_unit'),
        '#options' => [
          '%' => $this->t('Percents'),
          'px' => $this->t('Pixels'),
        ],
      ],
      'width' => [
        '#type' => 'number',
        '#title' => $this->t('Width'),
        '#default_value' => $this->getSetting('width'),
        '#size' => 5,
        '#maxlength' => 5,
        '#min' => 0,
        '#required' => TRUE,
      ],
      'height_unit' => [
        '#type' => 'radios',
        '#title' => $this->t('Height units'),
        '#default_value' => $this->getSetting('height_unit'),
        '#options' => [
          '%' => $this->t('Percents'),
          'px' => $this->t('Pixels'),
        ],
      ],
      'height' => [
        '#type' => 'number',
        '#title' => $this->t('Height'),
        '#default_value' => $this->getSetting('height'),
        '#size' => 5,
        '#maxlength' => 5,
        '#min' => 0,
        '#required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $widthName = $this->t('Width');
    $heightName = $this->t('Height');
    $summary[] = $widthName . ': ' . $this->getSetting('width') . $this->getSetting('width_unit');
    $summary[] = $heightName . ': ' . $this->getSetting('height') . $this->getSetting('height_unit');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    if (!parent::isApplicable($field_definition)) {
      return FALSE;
    }

    $extension_list = array_filter(preg_split('/\s+/', $field_definition->getSetting('file_extensions')));

    foreach ($extension_list as $extension) {
      if (OnlyofficeDocumentHelper::getDocumentType($extension)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $element = [
      '#attached' => [
        'library' => [
          'onlyoffice/onlyoffice.api',
          'onlyoffice/onlyoffice.preview',
        ],
      ],
    ];

    /** @var \Drupal\file\Entity\File $file */
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $extension = OnlyofficeDocumentHelper::getExtension($file->getFilename());

      if (OnlyofficeDocumentHelper::getDocumentType($extension)) {
        $editor_id = sprintf(
              '%s-%s-iframeOnlyofficeEditor',
              $file->getEntityTypeId(),
              $file->id()
          );

        $element[$delta] = ['#markup' => sprintf('<div id="%s" class="onlyoffice-editor"></div>', $editor_id)];

        $element['#attached']['drupalSettings']['onlyofficeData'][$editor_id] = [
          'config' => $this->getEditorConfig($file),
        ];
      }
    }

    return $element;
  }

  /**
   * Method getting configuration for document editor service.
   */
  private function getEditorConfig(File $file) {

    $editor_width = $this->getSetting('width') . $this->getSetting('width_unit');
    $editor_height = $this->getSetting('height') . $this->getSetting('height_unit');

    return OnlyofficeDocumentHelper::createEditorConfig(
          'embedded',
          OnlyofficeDocumentHelper::getEditingKey($file, TRUE),
          $file->getFilename(),
          OnlyofficeUrlHelper::getDownloadFileUrl($file),
          $file->getOwner()->getDisplayName(),
          $this->dateFormatter->format($file->getCreatedTime(), 'short'),
          FALSE,
          NULL,
          "view",
          $this->languageManager->getCurrentLanguage()->getId(),
          NULL,
          NULL,
          NULL,
          $editor_width,
          $editor_height
      );
  }

}
