<?php

namespace Drupal\onlyoffice\Plugin\Field\FieldWidget;

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

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\Entity\Media;
use Drupal\user\RoleStorageInterface;
use Drupal\onlyoffice\OnlyofficeAppConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A widget bar.
 *
 * @FieldWidget(
 *   id = "onlyoffice_editor_widget",
 *   label = @Translation("Onlyoffice Document widget"),
 *   field_types = {
 *     "onlyoffice_editor"
 *   }
 * )
 */
class OnlyofficeEditorWidget extends WidgetBase {

  /**
   * The role storage.
   *
   * @var \Drupal\user\RoleStorageInterface
   */
  protected $roleStorage;

  /**
   * Constructs a MediaLibraryWidget widget.
   *
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\user\RoleStorageInterface $role_storage
   *   The role storage.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, RoleStorageInterface $role_storage) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->roleStorage = $role_storage;
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
      $container->get('entity_type.manager')->getStorage('user_role')
    );
  }

  /**
   * Gets the roles to display in this form.
   *
   * @return \Drupal\user\RoleInterface[]
   *   An array of role objects.
   */
  protected function getRoles() {
    return $this->roleStorage->loadMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['#type'] = 'fieldset';

    $handlerSettings = $this->fieldDefinition->getSettings()['handler_settings'];

    $element['target_id'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'media',
      '#selection_handler' => 'default:media',
      '#selection_settings' => [
        'target_bundles' => $handlerSettings['target_bundles'] ?? NULL,
      ],
      '#title' => $this->t('Media'),
      '#default_value' => isset($items[$delta]->target_id) ? Media::load($items[$delta]->target_id) : NULL,
    ];

    $element['onlyoffice_permissions'] = [
      '#type' => 'table',
      '#header' => [$items[$delta]->getDataDefinition()->getPropertyDefinition('onlyoffice_permissions')->getLabel()],
      '#id' => 'onlyoffice_permissions',
      '#attributes' => ['class' => ['permissions', 'js-permissions']],
    ];

    foreach (OnlyofficeAppConfig::getOnlyofficePermissions() as $perm_item) {
      $element['onlyoffice_permissions']['#header'][] = [
        'data' => $this->t($perm_item['title']),
        'class' => ['checkbox'],
      ];
    }

    foreach ($this->getRoles() as $role) {
      $element['onlyoffice_permissions'][$role->id()]['description'] = [
        '#type' => 'inline_template',
        '#template' => '<div class="role"><span class="title">{{ title }}</span></div>',
        '#context' => [
          'title' => $role->label(),
        ],
      ];

      foreach (OnlyofficeAppConfig::getOnlyofficePermissions() as $perm => $perm_item) {
        $element['onlyoffice_permissions'][$role->id()][$perm] = [
          '#title' => $role->label(),
          '#title_display' => 'invisible',
          '#wrapper_attributes' => [
            'class' => ['checkbox'],
          ],
          '#type' => 'radio',
          '#default_value' => $items[$delta]->getOnlyofficePermissionForRole($role->id()),
          '#parents' => [
            $items[$delta]->getFieldDefinition()->getName(),
            $delta,
            'onlyoffice_permissions',
            $role->id(),
          ],
          '#return_value' => $perm,
        ];
        // Show a column of disabled but checked checkboxes.
        if ($role->isAdmin()) {
          $element['onlyoffice_permissions'][$role->id()][$perm]['#disabled'] = TRUE;
          $element['onlyoffice_permissions'][$role->id()][$perm]['#default_value'] = 'full_access';
        }
      }
    }

    return $element;
  }

}
