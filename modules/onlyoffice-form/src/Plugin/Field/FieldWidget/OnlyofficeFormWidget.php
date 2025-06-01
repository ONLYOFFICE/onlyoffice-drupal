<?php

namespace Drupal\onlyoffice_form\Plugin\Field\FieldWidget;

/**
 * Copyright (c) Ascensio System SIA 2025.
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

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\Attribute\FieldWidget;

/**
 * Plugin implementation of the 'onlyoffice_form_widget' widget.
 */
#[FieldWidget(
  id: "onlyoffice_form_widget",
  label: new \Drupal\Core\StringTranslation\TranslatableMarkup("ONLYOFFICE form"),
  field_types: [
    "onlyoffice_form"
  ]
)]
class OnlyofficeFormWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a OnlyofficeFormWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    $field_definition,
    array $settings,
    array $third_party_settings,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
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
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $target_id = $items[$delta]->target_id ?? NULL;
    $description = $items[$delta]->description ?? '';

    // Create a container for our form elements.
    $element += [
      '#type' => 'fieldset',
    ];

    // Media entity reference.
    $element['target_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('ONLYOFFICE form'),
      '#description' => $this->t('Select an existing ONLYOFFICE form or create a new one.'),
      '#target_type' => 'media',
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => ['onlyoffice_pdf_form'],
      ],
      '#default_value' => $target_id ? $this->entityTypeManager->getStorage('media')->load($target_id) : NULL,
      '#required' => $element['#required'],
      '#weight' => 0,
    ];

    // Create new form button.
    $element['create_new'] = [
      '#type' => 'link',
      '#title' => $this->t('Create new ONLYOFFICE form'),
      '#url' => Url::fromRoute('entity.onlyoffice_form.collection'),
      '#attributes' => [
        'class' => ['button'],
      ],
      '#weight' => 1,
    ];

    // Description field if enabled.
    if ($this->getFieldSetting('description_field')) {
      $element['description'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Description'),
        '#default_value' => $description,
        '#description' => $this->t('A brief description of this ONLYOFFICE form.'),
        '#maxlength' => 255,
        '#weight' => 2,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      if (empty($value['target_id'])) {
        unset($values[$delta]);
      }
    }
    return $values;
  }

}
