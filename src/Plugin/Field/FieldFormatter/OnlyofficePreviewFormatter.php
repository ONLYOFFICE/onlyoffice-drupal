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

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\file\Entity\File;
use Drupal\onlyoffice_connector\OnlyofficeDocumentHelper;
use Drupal\onlyoffice_connector\OnlyofficeUrlHelper;

/**
 * Plugin implementation of the 'onlyoffice_preview' formatter.
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
class OnlyofficePreviewFormatter extends OnlyofficeBaseFormatter {

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

    $element = parent::viewElements($items, $langcode);

    /** @var \Drupal\file\Entity\File $file */
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $extension = OnlyofficeDocumentHelper::getExtension($file->getFilename());

      if (OnlyofficeDocumentHelper::getDocumentType($extension)) {
        $editor_id = sprintf('%s-%s-iframeOnlyofficeEditor',
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

  private function getEditorConfig (File $file) {

    $editor_width = $this->getSetting('width') . $this->getSetting('width_unit');
    $editor_height = $this->getSetting('height') . $this->getSetting('height_unit');

    return OnlyofficeDocumentHelper::createEditorConfig(
      'embedded',
      OnlyofficeDocumentHelper::getEditingKey($file, true),
      $file->getFilename(),
      OnlyofficeUrlHelper::getDownloadFileUrl($file),
      document_info_owner: $file->getOwner()->getDisplayName(),
      document_info_uploaded: \Drupal::service('date.formatter')->format($file->getCreatedTime(), 'short'),
      editorConfig_lang: \Drupal::languageManager()->getCurrentLanguage()->getId(),
      editor_width: $editor_width,
      editor_height: $editor_height
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function needsEntityLoad(EntityReferenceItem $item) {
    return parent::needsEntityLoad($item) && $item->isDisplayed();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity) {
    // Only check access if the current file access control handler explicitly
    // opts in by implementing FileAccessFormatterControlHandlerInterface.
    $access_handler_class = $entity->getEntityType()->getHandlerClass('access');
    if (is_subclass_of($access_handler_class, '\Drupal\file\FileAccessFormatterControlHandlerInterface')) {
      return $entity->access('view', NULL, TRUE);
    }
    else {
      return AccessResult::allowed();
    }
  }
}
