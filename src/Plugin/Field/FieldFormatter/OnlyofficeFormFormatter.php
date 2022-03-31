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

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\RendererInterface;
use \Drupal\media\Entity\Media;
use Drupal\onlyoffice_connector\OnlyofficeDocumentHelper;
use Drupal\onlyoffice_connector\OnlyofficeUrlHelper;
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
   * Constructs an EntityReferenceEntityFormatter instance.
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
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, RendererInterface $renderer) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->renderer = $renderer;
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
      $container->get('renderer')
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

    $element = [
      '#attached' => [
        'library' => [
          'onlyoffice_connector/onlyoffice.api',
          'onlyoffice_connector/onlyoffice.editor'
        ]
      ]
    ];

    /** @var \Drupal\media\Entity\Media $media */
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $media) {
      $editor_id = sprintf('%s-%s-iframeOnlyofficeEditor',
        $media->getEntityTypeId(),
        $media->id()
      );

      $element[$delta] = ['#markup' => sprintf('<div id="%s" class="onlyoffice-editor"></div>', $editor_id)];
      $this->renderer->addCacheableDependency($element[$delta], $media);

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

  private function getEditorConfig (Media $media) {

    $file = $media->get(OnlyofficeDocumentHelper::getSourceFieldName($media))->entity;

    $account =  \Drupal::currentUser()->getAccount();

    $editor_width = $this->getSetting('width') . $this->getSetting('width_unit');
    $editor_height = $this->getSetting('height') . $this->getSetting('height_unit');

    return OnlyofficeDocumentHelper::createEditorConfig(
      'desktop',
      null,
      $file->getFilename(),
      OnlyofficeUrlHelper::getDownloadFileUrl($file),
      document_info_owner: $media->getOwner()->getDisplayName(),
      document_info_uploaded: \Drupal::service('date.formatter')->format($media->getCreatedTime(), 'short'),
      document_permissions_edit: true,
      editorConfig_callbackUrl: OnlyofficeUrlHelper::getCallbackFillFormUrl($media),
      editorConfig_mode: 'edit',
      editorConfig_lang: \Drupal::languageManager()->getCurrentLanguage()->getId(),
      editorConfig_user_id: $account->id(),
      editorConfig_user_name: $account->getDisplayName(),
      editor_width: $editor_width,
      editor_height: $editor_height
    );
  }
}
