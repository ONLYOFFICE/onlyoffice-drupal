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

namespace Drupal\onlyoffice_connector\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\media\Entity\MediaType;
use Drupal\onlyoffice_connector\OnlyofficeAppConfig;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a field type of Onlyoffice document.
 *
 * @FieldType(
 *   id = "onlyoffice_editor",
 *   label = @Translation("Onlyoffice editor"),
 *   default_formatter = "onlyoffice_editor",
 *   default_widget = "onlyoffice_editor_widget",
 *   list_class = "Drupal\onlyoffice_connector\Plugin\Field\FieldType\OnlyofficeEditorFieldItemList",
 * )
 */
class OnlyofficeEditorItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
        'target_type' => 'media',
      ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {

    $element = parent::storageSettingsForm($form, $form_state, $has_data);

    $element['target_type']['#disabled'] = true;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $properties = parent::propertyDefinitions($field_definition);

    $properties['onlyoffice_permissions'] = MapDataDefinition::create()->setLabel(t('Onlyoffice permissions'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $schema['columns']['onlyoffice_permissions'] = [
      'type' => 'blob',
      'size' => 'normal',
      'serialize' => TRUE,
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm (array $form, FormStateInterface $form_state) {
    $form = parent::fieldSettingsForm($form, $form_state);

    foreach ($form['handler']['handler_settings']['target_bundles']['#options'] as $target_bundle => $value) {
      if (MediaType::load($target_bundle)->getSource()->getPluginId() !== "file") {
        unset($form['handler']['handler_settings']['target_bundles']['#options'][$target_bundle]);
      }
    }
    return $form;
  }

  public function getOnlyofficePermissionForRole ($role_id) {
    if (isset($this->onlyoffice_permissions[$role_id])) {
      $permission = $this->onlyoffice_permissions[$role_id];
      return array_key_exists($permission, OnlyofficeAppConfig::getOnlyofficePermissions()) ? $permission : 'deny_access';
    }

    return 'deny_access';
  }

  public function getOnlyofficePermissionForUser (AccountInterface $account) {

    $roles = $account->getRoles();
    $maxPriority = 0;
    $resultOnlyofficePermission = 'deny_access';

    foreach ($roles as $role) {
     $onlyofficePermissionId = $this->getOnlyofficePermissionForRole($role);

     if (OnlyofficeAppConfig::getOnlyofficePermissions()[$onlyofficePermissionId]['priority'] > $maxPriority) {
       $resultOnlyofficePermission = $onlyofficePermissionId;
       $maxPriority = OnlyofficeAppConfig::getOnlyofficePermissions()[$onlyofficePermissionId]['priority'];
     }
    }

    return $resultOnlyofficePermission;
  }

}
