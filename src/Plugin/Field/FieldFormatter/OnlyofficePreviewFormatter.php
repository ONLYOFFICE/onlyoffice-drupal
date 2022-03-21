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

namespace Drupal\onlyoffice_connector\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Drupal\file\Entity\File;
use Drupal\onlyoffice_connector\OnlyofficeDocumentHelper;
use Drupal\onlyoffice_connector\OnlyofficeUrlHelper;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Plugin implementation of the 'file_document' formatter.
 *
 * @FieldFormatter(
 *   id = "onlyoffice_preview",
 *   label = @Translation("ONLYOFFICE Preview"),
 *   description = @Translation("Display the file using ONLYOFFICE Editor."),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class OnlyofficePreviewFormatter extends FileFormatterBase {

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
    $summary[] = $this->t('Width: %width%width_unit', [
      '%width' => $this->getSetting('width'),
      '%width_unit' => $this->getSetting('width_unit'),
    ]);
    $summary[] = $this->t('Height: %height%height_unit', [
      '%height' => $this->getSetting('height'),
      '%height_unit' => $this->getSetting('height_unit'),
    ]);
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
          'onlyoffice_connector/onlyoffice.api',
          'onlyoffice_connector/onlyoffice.preview'
        ]
      ]
    ];

    /** @var \Drupal\file\Entity\File $file */
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $extension = OnlyofficeDocumentHelper::getExtension($file->getFilename());

      if (OnlyofficeDocumentHelper::getDocumentType($extension)) {
        $editor_id = sprintf('%s-%s-iframeEditor',
          $file->getEntityTypeId(),
          $file->id()
        );

        $element[$delta] = ['#markup' => sprintf('<div id="%s"></div>', $editor_id)];

        $element['#attached']['drupalSettings']['onlyofficeData'][] = [
          'editor_id' => $editor_id,
          'config' => $this->getEditorConfig($file),
        ];
      }
    }

    return $element;
  }

  private function getEditorConfig (File $file) {

    $editor_width = $this->getSetting('width') . $this->getSetting('width_unit');
    $editor_height = $this->getSetting('height') . $this->getSetting('height_unit');

    return OnlyofficeDocumentHelper::createEditorConfig(
      'embedded',
      OnlyofficeDocumentHelper::getEditingKey($file, true),
      $file->getFilename(),
      OnlyofficeUrlHelper::getDownloadFileUrl($file),
      document_info_owner: $file->getOwner()->getDisplayName(),
      document_info_uploaded: $file->getCreatedTime(),
      editor_width: $editor_width,
      editor_height: $editor_height
    );
  }
}
