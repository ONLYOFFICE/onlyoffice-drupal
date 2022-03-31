<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2022
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 */

namespace Drupal\onlyoffice_connector\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Mime\MimeTypeGuesserInterface;


abstract class OnlyofficeBaseFormatter extends EntityReferenceFormatterBase {

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
    $summary[] = $this->t('Width') . ': ' . $this->getSetting('width') . $this->getSetting('width_unit');
    $summary[] = $this->t('Height') . ': ' . $this->getSetting('height') . $this->getSetting('height_unit');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $element = [
      '#attached' => [
        'library' => [
          'onlyoffice_connector/onlyoffice.api',
          'onlyoffice_connector/onlyoffice.editor'
        ]
      ]
    ];

    return $element;
  }
}
